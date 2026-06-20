<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductRecommendationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductRecommendationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createTestSchema(): void
    {
        foreach ([
            'wishlist_items',
            'reviews',
            'review_images',
            'product_tags',
            'product_discounts',
            'order_details',
            'orders',
            'product_images',
            'product_details',
            'products',
            'colors',
            'tags',
            'discounts',
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
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_recommend_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_recommend_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_recommend_primary');
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
            $table->text('description');
            $table->string('short_description')->nullable();
            $table->string('material')->nullable();
            $table->decimal('rating', 2, 1)->default(4.6);
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
            $table->decimal('original_price', 10, 2);
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

        Schema::create('product_tags', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('tag_id');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->decimal('total_price', 14, 2);
            $table->boolean('is_paid');
            $table->unsignedInteger('status');
            $table->string('receiver_name');
            $table->string('address');
            $table->string('phone_number');
            $table->unsignedInteger('customer_id');
            $table->timestamps();
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->unsignedInteger('order_id');
            $table->string('sku');
            $table->unsignedInteger('quantities');
            $table->decimal('unit_price', 14, 2);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('order_id');
            $table->string('sku');
            $table->string('order_detail_id')->unique();
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->timestamps();
        });

        DB::table('categories')->insert([
            ['category_id' => 1, 'name' => 'Office Chair', 'description' => 'Office chairs'],
            ['category_id' => 2, 'name' => 'Desk', 'description' => 'Desks'],
            ['category_id' => 3, 'name' => 'Lamp', 'description' => 'Lamps'],
            ['category_id' => 4, 'name' => 'Table', 'description' => 'Tables'],
        ]);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Furniture brand']);
        DB::table('colors')->insert(['color_id' => 1, 'name' => 'Black', 'code' => '#111111']);
        DB::table('tags')->insert(['tag_id' => 1, 'name' => 'modern']);

        $this->product(101, 'Current Office Chair', 1, 'Mesh', 1000000, 5);
        $this->product(102, 'Related Office Chair', 1, 'Mesh', 1050000, 5);
        $this->product(103, 'Far Table', 4, 'Wood', 3000000, 5);
        $this->product(104, 'Inactive Office Chair', 1, 'Mesh', 900000, 5, false);
        $this->product(105, 'Missing Material Chair', 1, null, 1100000, 5);
        $this->product(205, 'Room Desk', 2, 'Wood', 1500000, 5);
        $this->product(206, 'Room Lamp', 3, 'Metal', 700000, 5);
        $this->product(207, 'Out of Stock Desk', 2, 'Wood', 1300000, 0);
    }

    private function product(
        int $id,
        string $name,
        int $categoryId,
        ?string $material,
        int $price,
        int $stock,
        bool $active = true
    ): void {
        DB::table('products')->insert([
            'product_id' => $id,
            'name' => $name,
            'description' => $name . ' description',
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
            'name' => $name . ' Black',
            'description' => $name . ' variant',
            'color_id' => 1,
            'size' => '60x60x110cm',
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

    private function service(): ProductRecommendationService
    {
        return app(ProductRecommendationService::class);
    }

    private function user(): User
    {
        return User::create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => 'Customer',
            'last_name' => fake()->lastName(),
            'is_staff' => false,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    public function test_related_products_exclude_current_product(): void
    {
        $product = Product::with(['category', 'detailed_products.images', 'detailed_products.color', 'detailed_products.product_discounts.discount', 'approved_reviews'])
            ->find(101);

        $related = $this->service()->relatedProducts($product);

        $this->assertNotContains(101, $related->pluck('product_id')->all());
    }

    public function test_related_products_exclude_inactive_products(): void
    {
        $product = Product::with(['category', 'detailed_products.images', 'detailed_products.color', 'detailed_products.product_discounts.discount', 'approved_reviews'])
            ->find(101);

        $related = $this->service()->relatedProducts($product);

        $this->assertNotContains(104, $related->pluck('product_id')->all());
    }

    public function test_related_products_prioritize_same_category(): void
    {
        $product = Product::with(['category', 'detailed_products.images', 'detailed_products.color', 'detailed_products.product_discounts.discount', 'approved_reviews'])
            ->find(101);

        $related = $this->service()->relatedProducts($product);

        $this->assertSame(102, (int) $related->first()->product_id);
    }

    public function test_related_products_do_not_fail_when_material_is_missing(): void
    {
        $product = Product::with(['category', 'detailed_products.images', 'detailed_products.color', 'detailed_products.product_discounts.discount', 'approved_reviews'])
            ->find(105);

        $related = $this->service()->relatedProducts($product);

        $this->assertNotEmpty($related);
    }

    public function test_complete_the_room_returns_supplementary_categories(): void
    {
        $product = Product::with(['category', 'detailed_products.images', 'detailed_products.color', 'detailed_products.product_discounts.discount', 'approved_reviews'])
            ->find(101);

        $room = $this->service()->completeRoom($product);

        $this->assertContains('Desk', $room->pluck('category.name')->all());
        $this->assertNotContains('Office Chair', $room->pluck('category.name')->all());
    }

    public function test_complete_the_room_does_not_use_hard_coded_product_ids(): void
    {
        $product = Product::with(['category', 'detailed_products.images', 'detailed_products.color', 'detailed_products.product_discounts.discount', 'approved_reviews'])
            ->find(101);

        $room = $this->service()->completeRoom($product);

        $this->assertContains(205, $room->pluck('product_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_recently_viewed_removes_duplicates(): void
    {
        $this->get('/products/101')->assertOk();
        $this->get('/products/102')->assertOk();
        $this->get('/products/101')->assertOk();

        $this->assertSame([101, 102], session('recently_viewed_product_ids'));
    }

    public function test_recently_viewed_keeps_latest_first(): void
    {
        foreach ([101, 102, 103] as $productId) {
            $this->get('/products/'.$productId)->assertOk();
        }

        $this->assertSame([103, 102, 101], session('recently_viewed_product_ids'));
    }

    public function test_recently_viewed_filters_inactive_products(): void
    {
        $this->withSession(['recently_viewed_product_ids' => [104, 102]])
            ->get('/products/101')
            ->assertOk()
            ->assertDontSee('Inactive Office Chair')
            ->assertSee('Related Office Chair');
    }

    public function test_guest_recently_viewed_session_works(): void
    {
        $this->get('/products/101')->assertOk();
        $this->get('/products/102')->assertOk();

        $this->assertSame([102, 101], session('recently_viewed_product_ids'));
    }

    public function test_user_login_does_not_clear_recently_viewed_session(): void
    {
        $user = $this->user();

        $this->withSession(['recently_viewed_product_ids' => [101, 102]])
            ->actingAs($user)
            ->get('/products/103')
            ->assertOk();

        $this->assertSame([103, 101, 102], session('recently_viewed_product_ids'));
    }

    public function test_add_room_combo_excludes_out_of_stock_items(): void
    {
        $response = $this->postJson(route('recommendations.combo_cart_items'), [
            'product_ids' => [205, 207],
        ]);

        $response->assertOk()
            ->assertJsonPath('items.0.product_id', 205)
            ->assertJsonCount(1, 'items')
            ->assertJsonCount(1, 'skipped');
    }
}
