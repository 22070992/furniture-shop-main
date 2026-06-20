<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminAiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiInteriorComboTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
    }

    private function createTestSchema(): void
    {
        foreach ([
            'ai_combo_settings',
            'reviews',
            'product_discounts',
            'product_images',
            'product_details',
            'products',
            'colors',
            'discounts',
            'brands',
            'categories',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('avatar')->default('/images/default_avatar.jpg');
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->increments('brand_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->increments('discount_id');
            $table->string('title');
            $table->text('description');
            $table->decimal('percentage', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
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
            $table->text('description');
            $table->string('short_description')->nullable();
            $table->string('material')->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('quantities')->default(0);
            $table->unsignedInteger('amount_sold')->default(0);
            $table->unsignedInteger('brand_id');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('product_details', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->text('description');
            $table->unsignedInteger('color_id');
            $table->string('size');
            $table->decimal('original_price', 14, 2);
            $table->unsignedInteger('warranty_month');
            $table->unsignedInteger('quantities');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->increments('product_image_id');
            $table->string('sku');
            $table->string('url');
            $table->timestamps();
        });

        Schema::create('product_discounts', function (Blueprint $table) {
            $table->string('sku');
            $table->unsignedInteger('discount_id');
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('order_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('order_detail_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_combo_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('model')->default('test-ai');
            $table->unsignedTinyInteger('max_products')->default(3);
            $table->unsignedTinyInteger('candidate_limit')->default(12);
            $table->decimal('budget_overage_percent', 5, 2)->default(10);
            $table->json('room_category_mapping')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('categories')->insert([
            ['category_id' => 1, 'name' => 'Office Chair', 'description' => 'Office chair'],
            ['category_id' => 2, 'name' => 'Desk', 'description' => 'Desk'],
            ['category_id' => 3, 'name' => 'Lamp', 'description' => 'Lamp'],
            ['category_id' => 4, 'name' => 'Bookcase', 'description' => 'Bookcase'],
        ]);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Brand']);
        DB::table('colors')->insert([
            ['color_id' => 1, 'name' => 'Black', 'code' => '#111111'],
            ['color_id' => 2, 'name' => 'Beige', 'code' => '#d8c6a3'],
        ]);
        DB::table('ai_combo_settings')->insert([
            'enabled' => true,
            'model' => 'test-ai',
            'max_products' => 3,
            'candidate_limit' => 12,
            'budget_overage_percent' => 10,
            'room_category_mapping' => json_encode([
                'office' => ['Office Chair', 'Desk', 'Lamp', 'Bookcase'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product(101, 'Ergo Black Office Chair', 1, 'Mesh', 2000000, 5, true, 1);
        $this->product(102, 'Oak Standing Desk', 2, 'Oak wood', 5000000, 4, true, 2);
        $this->product(103, 'Slim Task Lamp', 3, 'Metal', 1200000, 7, true, 1);
        $this->product(104, 'Inactive Desk', 2, 'Oak wood', 3000000, 5, false, 2);
        $this->product(105, 'Sold Out Bookcase', 4, 'Wood', 2500000, 0, true, 2);
    }

    private function product(
        int $id,
        string $name,
        int $categoryId,
        string $material,
        int $price,
        int $stock,
        bool $active,
        int $colorId
    ): void {
        DB::table('products')->insert([
            'product_id' => $id,
            'name' => $name,
            'description' => $name . ' long description for testing office combo recommendations.',
            'short_description' => $name . ' short',
            'material' => $material,
            'category_id' => $categoryId,
            'quantities' => $stock,
            'amount_sold' => 0,
            'brand_id' => 1,
            'is_deleted' => !$active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_details')->insert([
            'sku' => 'SKU-' . $id,
            'product_id' => $id,
            'name' => $name . ' Variant',
            'description' => $name . ' variant',
            'color_id' => $colorId,
            'size' => '60 x 60 x 100 cm',
            'original_price' => $price,
            'warranty_month' => 12,
            'quantities' => $stock,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_images')->insert([
            'sku' => 'SKU-' . $id,
            'url' => '/images/product-' . $id . '.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ai_combo_never_returns_product_outside_database(): void
    {
        $this->fakeAi([
            ['product_id' => 999, 'sku' => 'SKU-999'],
            ['product_id' => 101, 'sku' => 'SKU-101'],
            ['product_id' => 102, 'sku' => 'SKU-102'],
            ['product_id' => 103, 'sku' => 'SKU-103'],
        ]);

        $ids = $this->recommend()->json('products.*.product_id');

        $this->assertNotContains(999, $ids);
        $this->assertContains(101, $ids);
    }

    public function test_ai_combo_never_returns_inactive_product(): void
    {
        $this->fakeAi([
            ['product_id' => 104, 'sku' => 'SKU-104'],
            ['product_id' => 101, 'sku' => 'SKU-101'],
            ['product_id' => 102, 'sku' => 'SKU-102'],
            ['product_id' => 103, 'sku' => 'SKU-103'],
        ]);

        $ids = $this->recommend()->json('products.*.product_id');

        $this->assertNotContains(104, $ids);
    }

    public function test_ai_combo_skips_out_of_stock_sku_when_choices_exist(): void
    {
        $this->fakeAi([
            ['product_id' => 105, 'sku' => 'SKU-105'],
            ['product_id' => 101, 'sku' => 'SKU-101'],
            ['product_id' => 102, 'sku' => 'SKU-102'],
            ['product_id' => 103, 'sku' => 'SKU-103'],
        ]);

        $skus = $this->recommend()->json('products.*.sku');

        $this->assertNotContains('SKU-105', $skus);
        $this->assertContains('SKU-101', $skus);
    }

    public function test_total_price_is_calculated_by_backend(): void
    {
        $this->fakeAi([
            ['product_id' => 101, 'sku' => 'SKU-101'],
            ['product_id' => 102, 'sku' => 'SKU-102'],
            ['product_id' => 103, 'sku' => 'SKU-103'],
        ]);

        $response = $this->recommend();

        $this->assertSame(8200000, (int) $response->json('total_price'));
    }

    public function test_invalid_ai_json_falls_back_to_rule_engine(): void
    {
        $this->app->instance(AdminAiService::class, new class extends AdminAiService {
            public function interiorComboRecommendation(array $input, array $candidates, array $settings): array|string
            {
                return '{invalid-json';
            }
        });

        $response = $this->recommend();

        $this->assertNotEmpty($response->json('products'));
        $this->assertContains($response->json('source'), ['ai+fallback', 'fallback']);
    }

    public function test_ai_error_uses_fallback(): void
    {
        $this->app->instance(AdminAiService::class, new class extends AdminAiService {
            public function interiorComboRecommendation(array $input, array $candidates, array $settings): array
            {
                throw new \RuntimeException('Provider timeout');
            }
        });

        $response = $this->recommend();

        $this->assertSame('fallback', $response->json('source'));
        $this->assertNotEmpty($response->json('products'));
    }

    public function test_add_combo_checks_stock(): void
    {
        $response = $this->postJson(route('ai-interior-combo.cart_items'), [
            'items' => [
                ['product_id' => 105, 'sku' => 'SKU-105', 'quantity' => 1],
                ['product_id' => 101, 'sku' => 'SKU-101', 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(['SKU-101'], $response->json('items.*.sku'));
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_add_combo_does_not_exceed_quantity(): void
    {
        $response = $this->postJson(route('ai-interior-combo.cart_items'), [
            'items' => [
                ['product_id' => 102, 'sku' => 'SKU-102', 'quantity' => 99],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('items.0.quantities', 4);
    }

    public function test_user_cannot_modify_price_through_request(): void
    {
        $response = $this->postJson(route('ai-interior-combo.cart_items'), [
            'items' => [
                ['product_id' => 102, 'sku' => 'SKU-102', 'quantity' => 1, 'unit_price' => 1, 'price' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('items.0.unit_price', 5000000);
    }

    public function test_admin_can_update_ai_combo_settings(): void
    {
        $admin = User::create([
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_staff' => true,
            'is_verified' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.ai-combo.update'), [
            'enabled' => '1',
            'model' => 'test-model',
            'max_products' => 4,
            'candidate_limit' => 20,
            'budget_overage_percent' => 15,
            'room_category_mapping' => json_encode(['office' => ['Desk', 'Lamp']]),
        ]);

        $response->assertRedirect(route('admin.ai-combo.index'));
        $this->assertDatabaseHas('ai_combo_settings', [
            'model' => 'test-model',
            'max_products' => 4,
        ]);
    }

    private function recommend()
    {
        return $this->postJson(route('ai-interior-combo.recommend'), [
            'room' => 'office',
            'budget' => 12000000,
            'style' => 'modern',
            'color' => 'black',
            'dimensions' => '3m x 4m',
            'prompt' => 'Need a productive office with lighting.',
        ])->assertOk();
    }

    private function fakeAi(array $combo): void
    {
        $this->app->instance(AdminAiService::class, new class($combo) extends AdminAiService {
            public function __construct(private array $combo)
            {
            }

            public function interiorComboRecommendation(array $input, array $candidates, array $settings): array
            {
                return [
                    'combo' => $this->combo,
                    'notes' => ['fake ai response'],
                ];
            }
        });
    }
}
