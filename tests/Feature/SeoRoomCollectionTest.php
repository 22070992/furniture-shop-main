<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeoRoomCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
    }

    public function test_product_slug_is_unique(): void
    {
        $first = Product::create($this->productPayload(1, ['name' => 'Mira Office Chair']));
        $second = Product::create($this->productPayload(2, ['name' => 'Mira Office Chair']));

        $this->assertSame('mira-office-chair', $first->slug);
        $this->assertSame('mira-office-chair-2', $second->slug);
    }

    public function test_old_product_id_url_redirects_to_slug(): void
    {
        $this->seedProduct(1, ['slug' => 'mira-office-chair']);

        $this->get('/products/1')
            ->assertStatus(301)
            ->assertRedirect(route('products.show', 'mira-office-chair'));
    }

    public function test_product_page_has_canonical_and_product_json_ld_without_fake_rating(): void
    {
        $this->seedProduct(1, ['slug' => 'mira-office-chair']);

        $response = $this->get('/products/mira-office-chair')->assertOk();

        $response->assertSee('<link rel="canonical" href="' . route('products.show', 'mira-office-chair') . '"', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertDontSee('"@type":"AggregateRating"', false);
    }

    public function test_aggregate_rating_outputs_only_for_approved_reviews(): void
    {
        $this->seedProduct(1, ['slug' => 'reviewed-office-chair']);
        $this->seedUser();
        Review::create([
            'user_id' => 1,
            'product_id' => 1,
            'order_id' => 1,
            'sku' => 'SKU-1',
            'order_detail_id' => '1::SKU-1',
            'rating' => 5,
            'content' => 'Verified and comfortable.',
            'status' => Review::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $this->get('/products/reviewed-office-chair')
            ->assertOk()
            ->assertSee('"@type":"AggregateRating"', false)
            ->assertSee('"reviewCount":1', false);
    }

    public function test_inactive_product_is_not_in_product_sitemap(): void
    {
        $this->seedProduct(1, ['slug' => 'active-chair']);
        $this->seedProduct(2, ['slug' => 'inactive-chair', 'is_deleted' => true], 'SKU-2');

        $this->get('/sitemap-products.xml')
            ->assertOk()
            ->assertSee(route('products.show', 'active-chair'), false)
            ->assertDontSee('inactive-chair', false);
    }

    public function test_sitemap_index_and_robots_do_not_expose_private_routes(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('sitemap.products'), false)
            ->assertSee(route('sitemap.categories'), false)
            ->assertSee(route('sitemap.collections'), false)
            ->assertDontSee('/admin', false)
            ->assertDontSee('/cart', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Sitemap: ' . route('sitemap.index'), false);
    }

    public function test_room_collection_only_shows_matching_active_products(): void
    {
        $this->seedProduct(1, ['name' => 'Mira Office Chair', 'slug' => 'mira-office-chair', 'category_id' => 1]);
        $this->seedProduct(2, ['name' => 'Aalto Sofa', 'slug' => 'aalto-sofa', 'category_id' => 2], 'SKU-2');

        $this->get('/collections/office')
            ->assertOk()
            ->assertSee('Mira Office Chair')
            ->assertDontSee('Aalto Sofa');
    }

    private function seedUser(): void
    {
        DB::table('users')->insert([
            'user_id' => 1,
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'avatar' => '/images/default_avatar.jpg',
            'is_staff' => false,
            'is_verified' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedProduct(int $id, array $overrides = [], string $sku = 'SKU-1'): void
    {
        DB::table('products')->insert($this->productPayload($id, $overrides));
        DB::table('product_details')->insert([
            'sku' => $sku,
            'product_id' => $id,
            'name' => 'Variant ' . $id,
            'description' => 'Variant description',
            'color_id' => 1,
            'size' => '60 x 60 x 100 cm',
            'original_price' => 2500000,
            'warranty_month' => 24,
            'quantities' => 10,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_images')->insert([
            'product_image_id' => $id,
            'sku' => $sku,
            'url' => '/images/products/' . $sku . '.jpg',
            'alt_text' => 'Product image',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function productPayload(int $id, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $id,
            'name' => 'Mira Office Chair ' . $id,
            'slug' => null,
            'description' => 'A supportive office chair with breathable mesh and stable frame for focused work sessions.',
            'short_description' => 'Supportive office chair for daily work.',
            'material' => 'Mesh and steel',
            'rating' => 4.6,
            'review_count' => 0,
            'meta_title' => null,
            'meta_description' => null,
            'og_image' => null,
            'category_id' => 1,
            'quantities' => 0,
            'amount_sold' => 0,
            'brand_id' => 1,
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function createSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'wishlist_items',
            'review_images',
            'reviews',
            'product_tags',
            'product_discounts',
            'discounts',
            'product_images',
            'product_details',
            'products',
            'colors',
            'tags',
            'brands',
            'categories',
            'room_collections',
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

        Schema::create('categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->text('description');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
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
            $table->string('slug')->nullable()->unique();
            $table->text('description');
            $table->string('short_description')->nullable();
            $table->string('material')->nullable();
            $table->decimal('rating', 2, 1)->default(4.6);
            $table->unsignedInteger('review_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
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
            $table->integer('quantities');
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
        });

        Schema::create('room_collections', function (Blueprint $table) {
            $table->id();
            $table->string('room_key')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('hero_image')->nullable();
            $table->json('category_keywords')->nullable();
            $table->json('featured_category_ids')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('categories')->insert([
            ['category_id' => 1, 'name' => 'Office Chair', 'slug' => 'office-chair', 'description' => 'Office chairs for workspaces.', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'Sofa', 'slug' => 'sofa', 'description' => 'Living room sofas.', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('brands')->insert(['brand_id' => 1, 'name' => 'Furemir', 'description' => 'Furniture brand', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('colors')->insert(['color_id' => 1, 'name' => 'Black', 'code' => '#111111', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('room_collections')->insert([
            'id' => 1,
            'room_key' => 'office',
            'slug' => 'office',
            'title' => 'Office',
            'description' => 'Office chairs, desks and storage.',
            'category_keywords' => json_encode(['office']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
