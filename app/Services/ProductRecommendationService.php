<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    private const RECENTLY_VIEWED_KEY = 'recently_viewed_product_ids';

    public function __construct(private ProductCatalogPresenter $presenter)
    {
    }

    public function relatedProducts(Product $product, int $limit = null): Collection
    {
        $limit ??= (int) config('shop.related_products_limit', 6);
        $product = $this->presenter->decorateProduct($product);
        $candidateLimit = max(40, $limit * 8);

        $candidates = $this->presenter->activeProductQuery()
            ->where('product_id', '<>', $product->product_id)
            ->take($candidateLimit)
            ->get();

        return $this->presenter->decorateProducts($candidates)
            ->map(function (Product $candidate) use ($product) {
                $candidate->recommendation_score = $this->scoreRelatedProduct($product, $candidate);

                return $candidate;
            })
            ->sortByDesc('recommendation_score')
            ->take($limit)
            ->values();
    }

    public function completeRoom(Product $product, int $limit = null): Collection
    {
        $limit ??= (int) config('shop.complete_room_limit', 4);
        $categoryNames = $this->completeRoomCategoryNames($product);
        $query = $this->presenter->activeProductQuery()
            ->where('product_id', '<>', $product->product_id);

        if ($categoryNames->isNotEmpty()) {
            $categoryIds = Category::whereIn('name', $categoryNames)->pluck('category_id');
            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            }
        } else {
            $query->where('category_id', '<>', $product->category_id);
        }

        $products = $this->presenter->decorateProducts($query->take(max(20, $limit * 6))->get());

        if ($products->count() < $limit && $categoryNames->isNotEmpty()) {
            $fallback = $this->presenter->activeProductQuery()
                ->where('product_id', '<>', $product->product_id)
                ->where('category_id', '<>', $product->category_id)
                ->whereNotIn('product_id', $products->pluck('product_id'))
                ->take($limit - $products->count())
                ->get();

            $products = $products->merge($this->presenter->decorateProducts($fallback));
        }

        return $products
            ->sortByDesc(fn ($candidate) => ($candidate->presentation->stock_total > 0 ? 100 : 0)
                + ($candidate->presentation->review_count * 2)
                + $candidate->presentation->rating_average)
            ->take($limit)
            ->values();
    }

    public function recentlyViewed(Request $request, ?int $excludeProductId = null): Collection
    {
        $ids = collect($request->session()->get(self::RECENTLY_VIEWED_KEY, []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => !$excludeProductId || $id !== (int) $excludeProductId)
            ->unique()
            ->values()
            ->all();

        return $this->presenter->getActiveProductsByIds($ids);
    }

    public function recordRecentlyViewed(Request $request, int $productId): void
    {
        $limit = (int) config('shop.recently_viewed_limit', 12);
        $ids = collect($request->session()->get(self::RECENTLY_VIEWED_KEY, []))
            ->prepend((int) $productId)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        $request->session()->put(self::RECENTLY_VIEWED_KEY, $ids);
    }

    public function comboCartItems(array $productIds): array
    {
        $products = $this->presenter->getActiveProductsByIds($productIds);
        $items = [];
        $skipped = [];

        foreach ($productIds as $productId) {
            $product = $products->firstWhere('product_id', (int) $productId);

            if (!$product) {
                $skipped[] = ['product_id' => (int) $productId, 'reason' => 'Product is not available.'];
                continue;
            }

            $detail = $product->detailed_products
                ->where('is_deleted', false)
                ->where('quantities', '>', 0)
                ->sortByDesc('quantities')
                ->first();

            if (!$detail) {
                $skipped[] = ['product_id' => (int) $productId, 'reason' => 'Product is out of stock.'];
                continue;
            }

            $items[] = [
                'product_id' => $product->product_id,
                'sku' => $detail->sku,
                'quantities' => 1,
                'color_name' => $detail->color?->name,
                'color_hex' => $detail->color?->code,
                'image' => $detail->images?->first()?->url,
            ];
        }

        return ['items' => $items, 'skipped' => $skipped];
    }

    private function scoreRelatedProduct(Product $product, Product $candidate): float
    {
        $score = 0;

        if ((int) $candidate->category_id === (int) $product->category_id) {
            $score += 50;
        }

        if ($product->material && $candidate->material && strtolower($product->material) === strtolower($candidate->material)) {
            $score += 20;
        }

        $sharedColors = $product->presentation->colors
            ->intersect($candidate->presentation->colors)
            ->count();
        $score += min(15, $sharedColors * 5);

        $basePrice = max(1, (float) $product->presentation->min_price);
        $priceGap = abs((float) $candidate->presentation->min_price - $basePrice) / $basePrice;
        $score += max(0, 15 - ($priceGap * 15));

        if ($candidate->presentation->stock_total > 0) {
            $score += 10;
        }

        if ($candidate->presentation->image) {
            $score += 5;
        }

        $score += min(10, $candidate->presentation->rating_average * 2);

        return round($score, 2);
    }

    private function completeRoomCategoryNames(Product $product): Collection
    {
        $categoryName = strtolower($product->category?->name ?? '');
        $mapping = collect(config('shop.complete_room_categories', []));
        $direct = collect($mapping->get($categoryName, []));

        if ($direct->isNotEmpty()) {
            return $direct;
        }

        $matched = $mapping
            ->first(fn ($targets, $source) => str_contains($categoryName, strtolower($source)));

        return collect($matched ?? []);
    }
}
