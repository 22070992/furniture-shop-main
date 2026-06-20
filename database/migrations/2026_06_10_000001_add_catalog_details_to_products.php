<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'short_description')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('short_description')->nullable()->after('description');
            });
        }

        if (!Schema::hasColumn('products', 'material')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('material')->nullable()->after('short_description');
            });
        }

        if (!Schema::hasColumn('products', 'rating')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('rating', 2, 1)->default(4.6)->after('material');
            });
        }

        if (!Schema::hasColumn('products', 'review_count')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('review_count')->default(0)->after('rating');
            });
        }

        if (!Schema::hasColumn('product_images', 'alt_text')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->string('alt_text')->nullable()->after('url');
            });
        }
    }

    public function down(): void
    {
        foreach (['review_count', 'rating', 'material', 'short_description'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('product_images', 'alt_text')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropColumn('alt_text');
            });
        }
    }
};
