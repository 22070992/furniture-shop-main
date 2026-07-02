<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiChatboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCatalogSchema();
        $this->seedCatalog();
    }

    public function test_chatbox_uses_openai_api_when_configured(): void
    {
        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('services.openai.model', 'gpt-test');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Chiếc ghế đen này phù hợp góc làm việc nhỏ vì có màu trung tính và giá dưới ngân sách.',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson(route('ai-chat.reply'), [
            'message' => 'Tôi cần ghế màu đen dưới 5 triệu',
            'history' => [
                ['role' => 'user', 'content' => 'Tôi đang setup góc làm việc nhỏ'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Chiếc ghế đen này phù hợp góc làm việc nhỏ vì có màu trung tính và giá dưới ngân sách.')
            ->assertJsonPath('products.0.type', 'Chair');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key')
            && $request['model'] === 'gpt-test'
            && collect($request['messages'])->contains(fn ($message) => str_contains($message['content'], 'Available product context JSON')));
    }

    public function test_chatbox_reports_missing_openai_key_without_calling_api(): void
    {
        config()->set('services.openai.api_key', null);
        Http::fake();

        $this->postJson(route('ai-chat.reply'), [
            'message' => 'Tư vấn bàn phòng khách',
        ])
            ->assertOk()
            ->assertJsonFragment([
                'reply' => 'Chatbox đã sẵn sàng dùng ChatGPT API, nhưng dự án chưa có OPENAI_API_KEY. Hãy điền key trong file .env rồi chạy php artisan optimize:clear.',
            ]);

        Http::assertNothingSent();
    }

    private function createCatalogSchema(): void
    {
        foreach ([
            'product_tags',
            'product_images',
            'product_details',
            'products',
            'tags',
            'colors',
            'brands',
            'product_types',
            'categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('index')->default(0);
            $table->timestamps();
        });

        Schema::create('product_types', function (Blueprint $table) {
            $table->increments('product_type_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('index')->default(0);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->increments('brand_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->increments('color_id');
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->increments('tag_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('product_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description');
            $table->string('short_description')->nullable();
            $table->string('material')->nullable();
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('product_type_id')->nullable();
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

        Schema::create('product_tags', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('tag_id');
            $table->timestamps();
        });
    }

    private function seedCatalog(): void
    {
        DB::table('categories')->insert([
            'category_id' => 1,
            'name' => 'Home Office',
            'description' => 'Office furniture',
            'index' => 1,
        ]);

        DB::table('product_types')->insert([
            'product_type_id' => 1,
            'name' => 'Chair',
            'slug' => 'chair',
            'description' => 'Chair products',
            'index' => 1,
        ]);

        DB::table('brands')->insert([
            'brand_id' => 1,
            'name' => 'Furemir',
            'description' => 'Furniture brand',
        ]);

        DB::table('colors')->insert([
            'color_id' => 1,
            'name' => 'Black',
            'code' => '#000000',
        ]);

        DB::table('tags')->insert([
            'tag_id' => 1,
            'name' => 'Office',
        ]);

        DB::table('products')->insert([
            'product_id' => 1,
            'name' => 'Nova Black Office Chair',
            'slug' => 'nova-black-office-chair',
            'description' => 'A comfortable black office chair for small work corners.',
            'short_description' => 'Black ergonomic office chair.',
            'material' => 'Fabric and metal',
            'category_id' => 1,
            'product_type_id' => 1,
            'amount_sold' => 10,
            'brand_id' => 1,
            'is_deleted' => false,
        ]);

        DB::table('product_details')->insert([
            'sku' => 'TEST-CHAIR-BLACK',
            'product_id' => 1,
            'name' => 'Nova Black Office Chair - Black',
            'description' => 'Black office chair SKU.',
            'color_id' => 1,
            'size' => '60 x 60 x 95 cm',
            'original_price' => 4200000,
            'warranty_month' => 12,
            'quantities' => 8,
            'is_deleted' => false,
        ]);

        DB::table('product_images')->insert([
            'sku' => 'TEST-CHAIR-BLACK',
            'url' => 'https://example.test/chair.jpg',
        ]);

        DB::table('product_tags')->insert([
            'product_id' => 1,
            'tag_id' => 1,
        ]);
    }
}
