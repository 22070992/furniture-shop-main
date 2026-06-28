<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method', 40)->default('cod')->after('is_paid')->index();
            }

            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 40)->default('unpaid')->after('payment_method')->index();
            }

            if (!Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status')->index();
            }
        });

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('order_id');
                $table->string('provider', 40);
                $table->string('transaction_reference', 120)->unique();
                $table->string('provider_transaction_id', 120)->nullable()->index();
                $table->decimal('amount', 14, 2);
                $table->string('status', 40)->default('pending')->index();
                $table->string('response_code', 20)->nullable()->index();
                $table->string('transaction_status', 20)->nullable()->index();
                $table->string('bank_code', 60)->nullable();
                $table->string('card_type', 60)->nullable();
                $table->string('order_info')->nullable();
                $table->json('callback_payload')->nullable();
                $table->timestamp('paid_at')->nullable()->index();
                $table->timestamps();

                $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
                $table->index(['provider', 'status']);
                $table->index(['order_id', 'provider']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('orders', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
