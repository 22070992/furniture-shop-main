<?php

namespace Tests\Feature;

use App\Services\AdminAiService;
use App\Services\OpenAiChatService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAiDashboardSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createDashboardSchema();
        $this->seedDashboardData();
    }

    public function test_dashboard_summary_uses_openai_when_available(): void
    {
        $openAi = new class extends OpenAiChatService {
            public array $messages = [];

            public function isConfigured(): bool
            {
                return true;
            }

            public function complete(array $messages, array $options = []): string
            {
                $this->messages = $messages;

                return json_encode([
                    'headline' => 'OpenAI headline for the current dashboard.',
                    'overview' => 'OpenAI overview based on the latest admin metrics.',
                    'insights' => [
                        'OpenAI insight about recent orders.',
                        'OpenAI insight about low stock.',
                        'OpenAI insight about conversion.',
                    ],
                    'actions' => [
                        'OpenAI action for pending orders.',
                        'OpenAI action for low-stock SKUs.',
                        'OpenAI action for top products.',
                    ],
                ]);
            }
        };

        $summary = (new AdminAiService())->dashboardSummary($openAi);

        $this->assertSame('openai', $summary['ai_provider']);
        $this->assertSame('OpenAI headline for the current dashboard.', $summary['headline']);
        $this->assertSame('OpenAI overview based on the latest admin metrics.', $summary['overview']);
        $this->assertSame('OpenAI insight about recent orders.', $summary['insights'][0]);
        $this->assertSame('OpenAI action for pending orders.', $summary['actions'][0]);
        $this->assertStringContainsString('newest data JSON', $openAi->messages[1]['content']);
    }

    private function createDashboardSchema(): void
    {
        foreach ([
            'site_visits',
            'order_details',
            'product_details',
            'products',
            'receiving_reports',
            'users',
            'orders',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->decimal('total_price', 14, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->string('payment_status')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_staff')->default(false);
            $table->timestamps();
        });

        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->increments('receiving_report_id');
            $table->decimal('total_price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('product_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('product_details', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->unsignedInteger('product_id');
            $table->string('name');
            $table->integer('quantities')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->unsignedInteger('order_id');
            $table->string('sku');
            $table->integer('quantities')->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->increments('site_visit_id');
            $table->string('session_id');
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    private function seedDashboardData(): void
    {
        $now = now();

        DB::table('orders')->insert([
            'order_id' => 1,
            'total_price' => 5000000,
            'is_paid' => true,
            'payment_status' => 'paid',
            'status' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->insert([
            'user_id' => 1,
            'email' => 'customer@example.com',
            'is_active' => true,
            'is_staff' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('products')->insert([
            'product_id' => 1,
            'name' => 'Sora Marble Nesting Tables',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('product_details')->insert([
            'sku' => 'SKU-1',
            'product_id' => 1,
            'name' => 'Sora Marble Nesting Tables - Walnut',
            'quantities' => 2,
            'is_deleted' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('order_details')->insert([
            'order_id' => 1,
            'sku' => 'SKU-1',
            'quantities' => 2,
            'unit_price' => 2500000,
        ]);

        DB::table('site_visits')->insert([
            'session_id' => 'session-1',
            'source' => 'direct',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
