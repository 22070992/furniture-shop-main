<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_types')) {
            Schema::create('product_types', function (Blueprint $table) {
                $table->increments('product_type_id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('index')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('products', 'product_type_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('product_type_id')->nullable()->after('category_id');
                $table->index('product_type_id', 'products_product_type_id_index');
                $table->foreign('product_type_id', 'products_product_type_id_foreign')
                    ->references('product_type_id')
                    ->on('product_types')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'product_type_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign('products_product_type_id_foreign');
                $table->dropIndex('products_product_type_id_index');
                $table->dropColumn('product_type_id');
            });
        }

        Schema::dropIfExists('product_types');
    }
};
