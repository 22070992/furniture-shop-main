<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'material',
        'rating',
        'review_count',
        'meta_title',
        'meta_description',
        'og_image',
        'category_id',
        'amount_sold',
        'brand_id',
        'is_deleted',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (!Schema::hasColumn('products', 'slug')) {
                return;
            }

            if (!filled($product->slug) || $product->isDirty('name')) {
                $product->slug = static::uniqueSlug($product->name, $product->getKey());
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'product';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->where('product_id', '<>', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function routeKey(): string
    {
        return $this->slug ?: (string) $this->product_id;
    }

    public function publicUrl(): string
    {
        return route('products.show', $this->routeKey());
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
    public function detailed_products()
    {
        return $this->hasMany(ProductDetail::class, 'product_id');
    }
    public function product_tags()
    {
        return $this->hasMany(ProductTag::class, 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function approved_reviews()
    {
        return $this->hasMany(Review::class, 'product_id')->approved();
    }

    public function wishlist_items()
    {
        return $this->hasMany(WishlistItem::class, 'product_id');
    }

    public function catalog_quality_issues()
    {
        return $this->hasMany(CatalogQualityIssue::class, 'product_id');
    }
}
