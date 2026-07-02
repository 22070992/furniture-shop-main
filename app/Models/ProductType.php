<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductType extends Model
{
    use HasFactory;

    protected $table = 'product_types';
    protected $primaryKey = 'product_type_id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'index',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductType $productType) {
            if (!Schema::hasColumn('product_types', 'slug')) {
                return;
            }

            if (!filled($productType->slug) || $productType->isDirty('name')) {
                $productType->slug = static::uniqueSlug($productType->name, $productType->getKey());
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'product-type';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->where('product_type_id', '<>', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'product_type_id');
    }
}
