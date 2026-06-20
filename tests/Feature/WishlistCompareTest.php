<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WishlistCompareTest extends TestCase
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
            'product_discounts',
            'order_details',
            'orders',
            'product_images',
            'product_details',
            'products',
            'colors',
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
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_wishlist_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_wishlist_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_wishlist_primary');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('name');
            $table->text('description');
            $table->integer('index')->default(0);
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

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->decimal('total_price', 14, 2);
            $table->boolean('is_paid');
            $table->unsignedInteger('status');
            $table->string('receiver_name');
            $table->string('address');
            $table->string('phone_number');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('created_by')->nullable();
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
            $table->text('admin_note')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
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
            $table->unique(['user_id', 'product_id']);
        });

        DB::table('categories')->insert(['category_id' => 1, 'name' => 'Office Chair', 'description' => 'Office chairs']);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Furniture brand']);
        DB::table('colors')->insert(['color_id' => 1, 'name' => 'Black', 'code' => '#111111']);

        for ($id = 1; $id <= 5; $id++) {
            $this->product($id, "Compare Product {$id}");
        }
    }

    private function user(): User
    {
        return User::create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => 'Customer',
            'last_name' => fake()->lastName(),
            'gender' => 1,
            'is_staff' => false,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    private function product(int $id, string $name, bool $active = true): void
    {
        DB::table('products')->insert([
            'product_id' => $id,
            'name' => $name,
            'description' => $name . ' description',
            'short_description' => $name . ' short description',
            'material' => 'Mesh',
            'category_id' => 1,
            'quantities' => 5,
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
            'original_price' => 1000000 + ($id * 1000),
            'warranty_month' => 12,
            'quantities' => 5,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_cannot_save_wishlist(): void
    {
        $this->post(route('wishlist.store'), ['product_id' => 1])
            ->assertRedirect(route('login'));
    }

    public function test_user_can_add_wishlist(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson(route('wishlist.store'), ['product_id' => 1])
            ->assertOk()
            ->assertJsonPath('wishlisted', true);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->getKey(),
            'product_id' => 1,
        ]);
    }

    public function test_duplicate_wishlist_is_not_added_twice(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson(route('wishlist.store'), ['product_id' => 1])->assertOk();
        $this->actingAs($user)->postJson(route('wishlist.store'), ['product_id' => 1])->assertOk();

        $this->assertSame(1, WishlistItem::where('user_id', $user->getKey())->where('product_id', 1)->count());
    }

    public function test_user_only_deletes_own_wishlist(): void
    {
        $owner = $this->user();
        $other = $this->user();
        WishlistItem::create(['user_id' => $owner->getKey(), 'product_id' => 1]);

        $this->actingAs($other)
            ->deleteJson(route('wishlist.destroy', 1))
            ->assertOk();

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $owner->getKey(),
            'product_id' => 1,
        ]);
    }

    public function test_missing_product_is_rejected_for_wishlist(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson(route('wishlist.store'), ['product_id' => 999])
            ->assertStatus(422);
    }

    public function test_guest_can_add_compare_by_session(): void
    {
        $this->postJson(route('compare.store'), ['product_id' => 1])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertSessionHas('compare_product_ids', [1]);
    }

    public function test_duplicate_compare_is_not_added_twice(): void
    {
        $this->postJson(route('compare.store'), ['product_id' => 1])->assertOk();
        $this->postJson(route('compare.store'), ['product_id' => 1])->assertOk();

        $this->assertSame([1], session('compare_product_ids'));
    }

    public function test_compare_cannot_exceed_four_products(): void
    {
        foreach ([1, 2, 3, 4] as $productId) {
            $this->postJson(route('compare.store'), ['product_id' => $productId])->assertOk();
        }

        $this->postJson(route('compare.store'), ['product_id' => 5])
            ->assertStatus(422);

        $this->assertSame([1, 2, 3, 4], session('compare_product_ids'));
    }

    public function test_can_remove_product_from_compare(): void
    {
        $this->withSession(['compare_product_ids' => [1, 2]])
            ->deleteJson(route('compare.destroy', 1))
            ->assertOk()
            ->assertJsonPath('ids', [2]);
    }

    public function test_compare_only_displays_valid_products(): void
    {
        $this->product(6, 'Deleted Compare Product', false);

        $this->withSession(['compare_product_ids' => [1, 6]])
            ->get(route('compare.index'))
            ->assertOk()
            ->assertSee('Compare Product 1')
            ->assertDontSee('Deleted Compare Product');
    }

    public function test_compare_rating_uses_only_approved_reviews(): void
    {
        $user = $this->user();
        Review::create([
            'user_id' => $user->getKey(),
            'product_id' => 1,
            'order_id' => 1,
            'sku' => 'SKU-1',
            'order_detail_id' => '1::SKU-1',
            'rating' => 5,
            'content' => 'Approved review',
            'status' => Review::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        Review::create([
            'user_id' => $user->getKey(),
            'product_id' => 1,
            'order_id' => 2,
            'sku' => 'SKU-1',
            'order_detail_id' => '2::SKU-1',
            'rating' => 1,
            'content' => 'Pending review',
            'status' => Review::STATUS_PENDING,
        ]);

        $this->withSession(['compare_product_ids' => [1]])
            ->get(route('compare.index'))
            ->assertOk()
            ->assertSee('5.0')
            ->assertSee('(1)')
            ->assertDontSee('1.0');
    }
}
