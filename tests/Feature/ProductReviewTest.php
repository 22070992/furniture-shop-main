<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductReviewTest extends TestCase
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
            'review_images',
            'reviews',
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
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_review_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_review_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_review_primary');
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
            $table->text('note')->nullable();
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
            $table->primary(['sku', 'order_id']);
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

        DB::table('categories')->insert(['category_id' => 1, 'name' => 'Office Chair', 'description' => 'Office chairs']);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Furniture brand']);
        DB::table('colors')->insert(['color_id' => 1, 'name' => 'Black', 'code' => '#111111']);
        DB::table('products')->insert([
            'product_id' => 1,
            'name' => 'Ergo Office Chair',
            'description' => 'Comfortable office chair',
            'short_description' => 'Comfortable office chair',
            'material' => 'Mesh',
            'category_id' => 1,
            'quantities' => 10,
            'amount_sold' => 0,
            'brand_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'product_id' => 2,
            'name' => 'Rattan Hanging Chair',
            'description' => 'Outdoor hanging chair',
            'short_description' => 'Outdoor hanging chair',
            'material' => 'Rattan',
            'category_id' => 1,
            'quantities' => 10,
            'amount_sold' => 0,
            'brand_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        ProductDetail::create([
            'sku' => 'SKU-CHAIR-BLACK',
            'product_id' => 1,
            'name' => 'Ergo Office Chair Black',
            'description' => 'Black ergonomic office chair',
            'color_id' => 1,
            'size' => '60x60x110cm',
            'original_price' => 1200000,
            'warranty_month' => 12,
            'quantities' => 10,
            'is_deleted' => false,
        ]);
    }

    private function user(bool $staff = false): User
    {
        return User::create([
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => $staff ? 'Admin' : 'Customer',
            'last_name' => fake()->lastName(),
            'gender' => 1,
            'is_staff' => $staff,
            'is_verified' => true,
            'is_active' => true,
        ]);
    }

    private function orderFor(User $user, int $status = 3, string $sku = 'SKU-CHAIR-BLACK'): Order
    {
        $order = Order::create([
            'total_price' => 1200000,
            'is_paid' => true,
            'status' => $status,
            'receiver_name' => $user->full_name(),
            'address' => '123 Review Street',
            'phone_number' => '0900000000',
            'customer_id' => $user->getKey(),
        ]);

        OrderDetail::create([
            'order_id' => $order->order_id,
            'sku' => $sku,
            'quantities' => 1,
            'unit_price' => 1200000,
        ]);

        return $order;
    }

    private function payload(Order $order, array $override = []): array
    {
        return array_merge([
            'order_id' => $order->order_id,
            'sku' => 'SKU-CHAIR-BLACK',
            'product_id' => 1,
            'rating' => 5,
            'content' => 'This chair is comfortable and matches the product description.',
        ], $override);
    }

    private function reviewFor(User $user, Order $order, array $override = []): Review
    {
        return Review::create(array_merge([
            'user_id' => $user->getKey(),
            'product_id' => 1,
            'order_id' => $order->order_id,
            'sku' => 'SKU-CHAIR-BLACK',
            'order_detail_id' => Review::orderDetailKey($order->order_id, 'SKU-CHAIR-BLACK'),
            'rating' => 5,
            'content' => 'This approved review should be visible publicly.',
            'status' => Review::STATUS_PENDING,
        ], $override));
    }

    public function test_guest_cannot_review(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);

        $this->post(route('reviews.store'), $this->payload($order))
            ->assertRedirect(route('login'));
    }

    public function test_user_cannot_review_another_users_order(): void
    {
        $owner = $this->user();
        $attacker = $this->user();
        $order = $this->orderFor($owner);

        $this->actingAs($attacker)
            ->post(route('reviews.store'), $this->payload($order))
            ->assertForbidden();
    }

    public function test_user_cannot_review_product_not_in_order_detail(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->post(route('reviews.store'), $this->payload($order, ['product_id' => 2]))
            ->assertStatus(422);
    }

    public function test_user_cannot_review_order_that_is_not_delivered(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user, 2);

        $this->actingAs($user)
            ->post(route('reviews.store'), $this->payload($order))
            ->assertForbidden();
    }

    public function test_valid_user_can_create_published_review_with_images(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->post(route('reviews.store'), $this->payload($order, [
                'images' => [UploadedFile::fake()->image('chair.jpg')],
            ]))
            ->assertSessionHas('success');

        $review = Review::first();
        $this->assertSame(Review::STATUS_APPROVED, $review->status);
        $this->assertNotNull($review->approved_at);
        $this->assertSame($user->getKey(), $review->user_id);
        $this->assertDatabaseHas('review_images', ['review_id' => $review->id]);
        Storage::disk('public')->assertExists($review->images()->first()->image_path);
    }

    public function test_cannot_review_same_order_detail_twice(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);
        $this->reviewFor($user, $order);

        $this->actingAs($user)
            ->post(route('reviews.store'), $this->payload($order))
            ->assertSessionHasErrors('order_detail');
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->post(route('reviews.store'), $this->payload($order, ['rating' => 6]))
            ->assertSessionHasErrors('rating');
    }

    public function test_pending_review_is_not_public(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);
        $this->reviewFor($user, $order, ['content' => 'Pending hidden review content.']);

        $this->get('/products/1')
            ->assertOk()
            ->assertDontSee('Pending hidden review content.');
    }

    public function test_approved_review_is_public_and_counted(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);
        $this->reviewFor($user, $order, [
            'content' => 'Approved public review content.',
            'status' => Review::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->get('/products/1')
            ->assertOk()
            ->assertSee('Approved public review content.')
            ->assertSee('5.0')
            ->assertSee('1 verified review');
    }

    public function test_rejected_review_is_not_public_or_counted(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);
        $this->reviewFor($user, $order, [
            'content' => 'Rejected hidden review content.',
            'status' => Review::STATUS_REJECTED,
        ]);

        $this->get('/products/1')
            ->assertOk()
            ->assertDontSee('Rejected hidden review content.')
            ->assertSee('No verified reviews yet');
    }

    public function test_normal_user_cannot_access_admin_reviews(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('admin.reviews.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_approve_and_reject_reviews(): void
    {
        $user = $this->user();
        $admin = $this->user(true);
        $order = $this->orderFor($user);
        $review = $this->reviewFor($user, $order);

        $this->actingAs($admin)
            ->patch(route('admin.reviews.update', $review), [
                'status' => Review::STATUS_APPROVED,
                'admin_note' => 'Looks valid.',
            ])
            ->assertSessionHas('success');

        $review->refresh();
        $this->assertSame(Review::STATUS_APPROVED, $review->status);
        $this->assertSame($admin->getKey(), $review->approved_by);
        $this->assertNotNull($review->approved_at);

        $this->actingAs($admin)
            ->patch(route('admin.reviews.update', $review), [
                'status' => Review::STATUS_REJECTED,
                'admin_note' => 'Image does not match.',
            ])
            ->assertSessionHas('success');

        $review->refresh();
        $this->assertSame(Review::STATUS_REJECTED, $review->status);
        $this->assertNull($review->approved_by);
        $this->assertNull($review->approved_at);
    }
}
