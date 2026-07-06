<?php

use App\Models\Review;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureOrderDetailsTable();
        $this->ensureReviewsTable();
        $this->ensureReviewImagesTable();
        $this->publishDeliveredPendingReviews();
    }

    public function down(): void
    {
        // This repair migration intentionally does not drop tables or revert review
        // statuses because those tables may contain real order/review data.
    }

    private function ensureOrderDetailsTable(): void
    {
        if (Schema::hasTable('order_details') || !Schema::hasTable('orders') || !Schema::hasTable('product_details')) {
            return;
        }

        Schema::create('order_details', function (Blueprint $table) {
            $table->unsignedInteger('order_id');
            $table->string('sku');
            $table->unsignedInteger('quantities');
            $table->decimal('unit_price', 14, 2);
            $table->timestamps();

            $table->primary(['sku', 'order_id']);
            $table->unique(['sku', 'order_id']);
            $table->foreign('order_id')->references('order_id')->on('orders');
            $table->foreign('sku')->references('sku')->on('product_details');
        });
    }

    private function ensureReviewsTable(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        if (
            !Schema::hasTable('users')
            || !Schema::hasTable('products')
            || !Schema::hasTable('orders')
            || !Schema::hasTable('product_details')
        ) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('order_id');
            $table->string('sku');
            $table->string('order_detail_id');
            $table->unsignedTinyInteger('rating');
            $table->text('content');
            $table->string('status', 20)->default(Review::STATUS_PENDING);
            $table->text('admin_note')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('product_id')->references('product_id')->on('products')->cascadeOnDelete();
            $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete();
            $table->foreign('sku')->references('sku')->on('product_details')->cascadeOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->nullOnDelete();

            $table->unique('order_detail_id');
            $table->unique(['order_id', 'sku']);
            $table->index('product_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['product_id', 'status']);
        });
    }

    private function ensureReviewImagesTable(): void
    {
        if (Schema::hasTable('review_images') || !Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id');
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
        });
    }

    private function publishDeliveredPendingReviews(): void
    {
        if (!Schema::hasTable('reviews') || !Schema::hasTable('orders')) {
            return;
        }

        DB::table('reviews')
            ->where('status', Review::STATUS_PENDING)
            ->whereIn('order_id', DB::table('orders')->where('status', 3)->select('order_id'))
            ->update([
                'status' => Review::STATUS_APPROVED,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
    }
};
