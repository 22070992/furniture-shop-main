<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('review_count');
            }
            if (!Schema::hasColumn('products', 'meta_description')) {
                $table->string('meta_description', 320)->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('products', 'og_image')) {
                $table->string('og_image')->nullable()->after('meta_description');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('categories', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('description');
            }
            if (!Schema::hasColumn('categories', 'meta_description')) {
                $table->string('meta_description', 320)->nullable()->after('meta_title');
            }
        });

        $this->backfillSlugs('products', 'product_id');
        $this->backfillSlugs('categories', 'category_id');

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug', 'products_slug_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('slug', 'categories_slug_unique');
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

        $now = now();
        DB::table('room_collections')->insert([
            [
                'room_key' => 'living_room',
                'slug' => 'living-room',
                'title' => 'Living Room',
                'description' => 'Curated sofas, coffee tables, lounge chairs and media storage for relaxed modern living rooms.',
                'category_keywords' => json_encode(['living', 'sofa', 'coffee table', 'lounge chair', 'media', 'rug', 'lamp']),
                'meta_title' => 'Living Room Furniture | Furemir',
                'meta_description' => 'Shop living room furniture including sofas, coffee tables, lounge chairs and storage pieces for modern homes.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_key' => 'bedroom',
                'slug' => 'bedroom',
                'title' => 'Bedroom',
                'description' => 'Beds, nightstands, wardrobes and lighting selected for restful bedroom spaces.',
                'category_keywords' => json_encode(['bedroom', 'bed', 'nightstand', 'wardrobe', 'dresser', 'lamp']),
                'meta_title' => 'Bedroom Furniture | Furemir',
                'meta_description' => 'Discover bedroom furniture with beds, nightstands, wardrobes and cozy finishing pieces.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_key' => 'dining_room',
                'slug' => 'dining-room',
                'title' => 'Dining Room',
                'description' => 'Dining tables, chairs, cabinets and lighting for warm everyday meals and hosting.',
                'category_keywords' => json_encode(['dining', 'dining table', 'dining chair', 'cabinet', 'sideboard', 'light']),
                'meta_title' => 'Dining Room Furniture | Furemir',
                'meta_description' => 'Browse dining room furniture including tables, chairs, cabinets and lights for practical dining spaces.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_key' => 'office',
                'slug' => 'office',
                'title' => 'Office',
                'description' => 'Office chairs, desks, bookcases and task lighting for productive home workspaces.',
                'category_keywords' => json_encode(['office', 'desk', 'office chair', 'bookcase', 'shelf', 'task lamp']),
                'meta_title' => 'Office Furniture | Furemir',
                'meta_description' => 'Find office furniture for home workspaces, from ergonomic chairs to desks, storage and lighting.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_collections');

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_slug_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_slug_unique');
        });

        foreach (['og_image', 'meta_description', 'meta_title', 'slug'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['meta_description', 'meta_title', 'slug'] as $column) {
            if (Schema::hasColumn('categories', $column)) {
                Schema::table('categories', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function backfillSlugs(string $table, string $primaryKey): void
    {
        $used = [];

        DB::table($table)
            ->select($primaryKey, 'name')
            ->orderBy($primaryKey)
            ->get()
            ->each(function ($row) use ($table, $primaryKey, &$used) {
                $base = Str::slug($row->name ?: $table . '-' . $row->{$primaryKey});
                $base = $base !== '' ? $base : $table . '-' . $row->{$primaryKey};
                $slug = $base;
                $counter = 2;

                while (isset($used[$slug])) {
                    $slug = $base . '-' . $counter;
                    $counter++;
                }

                $used[$slug] = true;
                DB::table($table)->where($primaryKey, $row->{$primaryKey})->update(['slug' => $slug]);
            });
    }
};
