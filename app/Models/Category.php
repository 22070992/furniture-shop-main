<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    protected $table = 'categories';
    protected $primaryKey = 'category_id';
    protected $fillable = ['name', 'slug', 'description', 'meta_title', 'meta_description', 'index', 'parent_id'];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (!Schema::hasColumn('categories', 'slug')) {
                return;
            }

            if (!filled($category->slug) || $category->isDirty('name')) {
                $category->slug = static::uniqueSlug($category->name, $category->getKey());
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'category';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->where('category_id', '<>', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }


    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
    public function children_categories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
