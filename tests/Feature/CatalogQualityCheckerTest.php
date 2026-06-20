<?php

namespace Tests\Feature;

use App\Models\CatalogQualityIssue;
use App\Models\CatalogQualityRun;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminAiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogQualityCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createTestSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_rule_checker_detects_missing_images(): void
    {
        $this->seedProduct(1, ['name' => 'Aria Office Chair'], ['images' => 0]);

        $run = $this->runRuleScan();

        $this->assertTrue($run->issues()->where('issue_code', 'PRODUCT_MISSING_IMAGES')->exists());
    }

    public function test_rule_checker_detects_missing_description(): void
    {
        $this->seedProduct(1, ['description' => ''], ['images' => 2]);

        $run = $this->runRuleScan();

        $this->assertTrue($run->issues()->where('issue_code', 'PRODUCT_MISSING_DESCRIPTION')->exists());
    }

    public function test_rule_checker_detects_product_without_sku(): void
    {
        $this->seedProduct(1, ['name' => 'No SKU Chair'], null);

        $run = $this->runRuleScan();

        $this->assertTrue($run->issues()->where('issue_code', 'PRODUCT_HAS_NO_SKU')->exists());
    }

    public function test_rule_checker_detects_invalid_price(): void
    {
        $this->seedProduct(1, [], ['original_price' => 0, 'images' => 2]);
        $this->seedProduct(2, ['name' => 'Negative Price Chair'], ['sku' => 'SKU-2', 'original_price' => -1000, 'images' => 2]);

        $run = $this->runRuleScan();

        $this->assertTrue($run->issues()->where('issue_code', 'SKU_PRICE_ZERO')->exists());
        $this->assertTrue($run->issues()->where('issue_code', 'SKU_PRICE_NEGATIVE')->exists());
    }

    public function test_normal_user_cannot_run_scan(): void
    {
        $this->actingAs($this->user(false))
            ->post(route('admin.catalog-quality.runs.store'), [
                'scan_type' => 'rule',
                'scope' => 'all',
                'status' => 'all',
            ])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('catalog_quality_runs', 0);
    }

    public function test_admin_can_run_scan(): void
    {
        $this->seedProduct(1, ['description' => ''], ['images' => 0]);

        $this->actingAs($this->user(true))
            ->post(route('admin.catalog-quality.runs.store'), [
                'scan_type' => 'rule',
                'scope' => 'all',
                'status' => 'all',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('catalog_quality_runs', [
            'status' => CatalogQualityRun::STATUS_COMPLETED,
            'scan_type' => CatalogQualityRun::SCAN_RULE,
        ]);
        $this->assertDatabaseHas('catalog_quality_issues', ['issue_code' => 'PRODUCT_MISSING_IMAGES']);
    }

    public function test_ai_error_does_not_make_rule_scan_fail(): void
    {
        $this->seedProduct(1, ['description' => ''], ['images' => 0]);
        $this->app->instance(AdminAiService::class, new class extends AdminAiService {
            public function catalogQualityTextReview(Product $product): array
            {
                throw new \RuntimeException('Provider timeout');
            }
        });

        $this->actingAs($this->user(true))
            ->post(route('admin.catalog-quality.runs.store'), [
                'scan_type' => 'ai',
                'scope' => 'all',
                'status' => 'all',
            ])
            ->assertRedirect();

        $run = CatalogQualityRun::first();

        $this->assertSame(CatalogQualityRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->error_message);
        $this->assertDatabaseHas('catalog_quality_issues', ['issue_code' => 'PRODUCT_MISSING_IMAGES']);
        $this->assertDatabaseHas('catalog_quality_issues', ['issue_code' => 'AI_PRODUCT_SCAN_FAILED']);
    }

    public function test_invalid_ai_response_is_handled(): void
    {
        $this->seedProduct(1, [], ['images' => 2]);
        $this->app->instance(AdminAiService::class, new class extends AdminAiService {
            public function catalogQualityTextReview(Product $product): array
            {
                return [['not_an_issue' => true]];
            }
        });

        $this->actingAs($this->user(true))
            ->post(route('admin.catalog-quality.runs.store'), [
                'scan_type' => 'ai',
                'scope' => 'all',
                'status' => 'all',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('catalog_quality_issues', ['issue_code' => 'AI_INVALID_RESPONSE']);
    }

    public function test_ai_does_not_auto_modify_product(): void
    {
        $this->seedProduct(1, [
            'description' => 'Short text',
            'short_description' => '',
            'material' => '',
        ], ['images' => 2]);

        $this->actingAs($this->user(true))
            ->post(route('admin.catalog-quality.runs.store'), [
                'scan_type' => 'ai',
                'scope' => 'all',
                'status' => 'all',
            ])
            ->assertRedirect();

        $product = Product::find(1);

        $this->assertSame('', $product->short_description);
        $this->assertSame('', $product->material);
        $this->assertDatabaseHas('catalog_quality_issues', ['source' => 'ai', 'field_name' => 'short_description']);
    }

    public function test_apply_suggestion_requires_admin(): void
    {
        $this->seedProduct(1, ['short_description' => ''], ['images' => 2]);
        $issue = CatalogQualityIssue::create([
            'run_id' => CatalogQualityRun::create([
                'started_by' => $this->user(true)->getKey(),
                'status' => 'completed',
                'scan_type' => 'ai',
                'started_at' => now(),
                'completed_at' => now(),
            ])->id,
            'product_id' => 1,
            'issue_code' => 'AI_SHORT_DESCRIPTION_SUGGESTION',
            'severity' => 'low',
            'source' => 'ai',
            'field_name' => 'short_description',
            'suggestion' => 'A better short description.',
            'status' => 'open',
        ]);

        $this->actingAs($this->user(false))
            ->post(route('admin.catalog-quality.issues.apply', $issue))
            ->assertRedirect(route('login'));

        $this->assertSame('', Product::find(1)->short_description);
    }

    public function test_issue_resolved_and_ignored_work(): void
    {
        $this->seedProduct(1, ['description' => ''], ['images' => 0]);
        $run = $this->runRuleScan();
        $issue = $run->issues()->first();

        $this->actingAs($this->user(true))
            ->patch(route('admin.catalog-quality.issues.update', $issue), ['status' => 'ignored'])
            ->assertRedirect();

        $this->assertDatabaseHas('catalog_quality_issues', [
            'id' => $issue->id,
            'status' => CatalogQualityIssue::STATUS_IGNORED,
        ]);

        $this->actingAs($this->user(true))
            ->patch(route('admin.catalog-quality.issues.update', $issue), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertDatabaseHas('catalog_quality_issues', [
            'id' => $issue->id,
            'status' => CatalogQualityIssue::STATUS_RESOLVED,
        ]);
    }

    public function test_scan_does_not_create_duplicate_issues_inside_one_run(): void
    {
        $this->seedProduct(1, ['description' => ''], ['images' => 0]);

        $run = $this->runRuleScan();
        $keys = $run->issues
            ->map(fn ($issue) => implode('|', [
                $issue->product_id ?? 'run',
                $issue->sku ?? 'product',
                $issue->issue_code,
                $issue->source,
                $issue->field_name ?? 'field',
            ]));

        $this->assertSame($keys->count(), $keys->unique()->count());
    }

    private function runRuleScan(): CatalogQualityRun
    {
        $this->actingAs($this->user(true))
            ->post(route('admin.catalog-quality.runs.store'), [
                'scan_type' => 'rule',
                'scope' => 'all',
                'status' => 'all',
            ])
            ->assertRedirect();

        return CatalogQualityRun::latest('id')->first();
    }

    private function user(bool $admin): User
    {
        return User::create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => $admin ? 'Admin' : 'Customer',
            'last_name' => fake()->lastName(),
            'avatar' => '/images/default_avatar.jpg',
            'gender' => 1,
            'is_staff' => $admin,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    private function seedProduct(int $id, array $productOverrides = [], ?array $skuOverrides = []): void
    {
        DB::table('products')->insert(array_merge([
            'product_id' => $id,
            'name' => 'Ergo Office Chair ' . $id,
            'description' => 'A supportive office chair with breathable mesh, molded foam and stable frame for focused work sessions.',
            'short_description' => 'Supportive office chair for daily work.',
            'material' => 'Mesh and steel',
            'rating' => 4.6,
            'review_count' => 0,
            'category_id' => 1,
            'quantities' => 0,
            'amount_sold' => 0,
            'brand_id' => 1,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $productOverrides));

        if ($skuOverrides === null) {
            return;
        }

        $sku = $skuOverrides['sku'] ?? 'SKU-' . $id;
        DB::table('product_details')->insert([
            'sku' => $sku,
            'product_id' => $id,
            'name' => $skuOverrides['name'] ?? 'Black variant',
            'description' => $skuOverrides['description'] ?? 'Variant description',
            'color_id' => $skuOverrides['color_id'] ?? 1,
            'size' => $skuOverrides['size'] ?? '60 x 60 x 110 cm',
            'original_price' => $skuOverrides['original_price'] ?? 2500000,
            'warranty_month' => $skuOverrides['warranty_month'] ?? 24,
            'quantities' => $skuOverrides['quantities'] ?? 10,
            'is_deleted' => $skuOverrides['is_deleted'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 0; $i < ($skuOverrides['images'] ?? 1); $i++) {
            DB::table('product_images')->insert([
                'product_image_id' => ($id * 10) + $i,
                'sku' => $sku,
                'url' => '/images/products/' . $sku . '-' . $i . '.jpg',
                'alt_text' => 'Product image',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createTestSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'catalog_quality_issues',
            'catalog_quality_runs',
            'product_images',
            'product_details',
            'products',
            'colors',
            'tags',
            'brands',
            'categories',
            'role_has_permissions',
            'model_has_permissions',
            'model_has_roles',
            'roles',
            'permissions',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('avatar')->default('/images/default_avatar.jpg');
            $table->date('birth_date')->nullable();
            $table->boolean('gender')->nullable();
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_catalog_quality_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_catalog_quality_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_catalog_quality_primary');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('name');
            $table->text('description');
            $table->integer('index')->default(0);
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->increments('brand_id');
            $table->string('name');
            $table->text('description');
            $table->integer('index')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('tag_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->increments('color_id');
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('product_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->string('material')->nullable();
            $table->decimal('rating', 2, 1)->default(4.6);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('quantities')->default(0);
            $table->unsignedInteger('amount_sold')->default(0);
            $table->unsignedInteger('brand_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('product_details', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->unsignedInteger('product_id');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('color_id')->nullable();
            $table->string('size')->nullable();
            $table->decimal('original_price', 14, 2);
            $table->unsignedInteger('warranty_month')->default(12);
            $table->integer('quantities')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->increments('product_image_id');
            $table->string('sku');
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_quality_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('started_by')->nullable();
            $table->string('status', 20)->default('running');
            $table->string('scan_type', 20)->default('rule');
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('total_issues')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_quality_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('issue_code', 80);
            $table->string('severity', 20);
            $table->string('source', 20);
            $table->string('field_name')->nullable();
            $table->text('current_value')->nullable();
            $table->text('suggestion')->nullable();
            $table->string('status', 20)->default('open');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::table('categories')->insert([
            ['category_id' => 1, 'name' => 'Office Chair', 'description' => 'Office chairs', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'Sofa', 'description' => 'Sofas', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Furniture brand', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('colors')->insert(['color_id' => 1, 'name' => 'Black', 'code' => '#111111', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tags')->insert(['tag_id' => 1, 'name' => 'Modern', 'created_at' => now(), 'updated_at' => now()]);
    }
}
