<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductCatalogPresenter
{
    public function activeProductQuery(): Builder
    {
        return Product::with([
            'category',
            'brand',
            'approved_reviews',
            'detailed_products' => function ($query) {
                $query->where('is_deleted', 0)
                    ->with(['images', 'color', 'product_discounts.discount']);
            },
        ])
            ->where('is_deleted', false)
            ->whereHas('detailed_products', function ($query) {
                $query->where('is_deleted', 0);
            });
    }

    public function findActiveProduct(int $productId): ?Product
    {
        return $this->activeProductQuery()
            ->where('product_id', $productId)
            ->first();
    }

    public function getActiveProductsByIds(array $productIds): Collection
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = $this->activeProductQuery()
            ->whereIn('product_id', $ids)
            ->get()
            ->sortBy(fn ($product) => $ids->search((int) $product->product_id))
            ->values();

        return $this->decorateProducts($products);
    }

    public function decorateProduct(Product $product): Product
    {
        return $this->decorateProducts(collect([$product]))->first();
    }

    public function decorateProducts(Collection $products): Collection
    {
        return $products->map(function (Product $product) {
            $details = $product->detailed_products;
            $primaryDetail = $details->sortByDesc('quantities')->first() ?? $details->first();
            $prices = $details->map(fn ($detail) => $this->discountedPrice($detail))->filter();
            $approvedReviews = $product->approved_reviews;

            $product->presentation = (object) [
                'image' => $primaryDetail?->images?->first()?->url,
                'min_price' => $prices->min() ?? 0,
                'max_price' => $prices->max() ?? 0,
                'colors' => $details->map(fn ($detail) => $detail->color?->name)->filter()->unique()->values(),
                'color_codes' => $details->map(fn ($detail) => $detail->color?->code)->filter()->unique()->values(),
                'sizes' => $details->map(fn ($detail) => $detail->size)->filter()->unique()->values(),
                'sku_count' => $details->count(),
                'stock_total' => $details->sum('quantities'),
                'rating_average' => $approvedReviews->count() > 0 ? round($approvedReviews->avg('rating'), 1) : 0,
                'review_count' => $approvedReviews->count(),
                'cart_detail' => $primaryDetail,
            ];

            return $product;
        });
    }

    public function discountedPrice($detail): float
    {
        $discount = $detail->product_discounts
            ->filter(fn ($productDiscount) => $productDiscount->discount?->is_currently_active())
            ->sum('discount.percentage');

        return (float) max(0, $detail->original_price - ($detail->original_price * $discount / 100));
    }
}
