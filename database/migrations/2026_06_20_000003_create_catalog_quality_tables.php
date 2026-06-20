<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->foreign('started_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('scan_type');
            $table->index('started_at');
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

            $table->foreign('run_id')->references('id')->on('catalog_quality_runs')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->nullOnDelete();
            $table->foreign('sku')->references('sku')->on('product_details')->nullOnDelete();
            $table->index('product_id');
            $table->index('sku');
            $table->index('issue_code');
            $table->index('severity');
            $table->index('source');
            $table->index('status');
            $table->index('created_at');
            $table->index(['run_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_quality_issues');
        Schema::dropIfExists('catalog_quality_runs');
    }
};
