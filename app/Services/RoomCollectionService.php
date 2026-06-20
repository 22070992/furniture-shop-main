<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\RoomCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoomCollectionService
{
    public function __construct(private readonly ProductCatalogPresenter $presenter)
    {
    }

    public function categoriesFor(RoomCollection $collection): Collection
    {
        if (!empty($collection->featured_category_ids)) {
            return Category::whereIn('category_id', $collection->featured_category_ids)
                ->orderBy('name')
                ->get();
        }

        $keywords = collect($collection->category_keywords ?? [])
            ->map(fn ($keyword) => Str::ascii(Str::lower($keyword)))
            ->filter()
            ->values();

        if ($keywords->isEmpty()) {
            return collect();
        }

        return Category::query()
            ->orderBy('name')
            ->get()
            ->filter(function (Category $category) use ($keywords) {
                $haystack = Str::ascii(Str::lower($category->name . ' ' . $category->description));

                return $keywords->contains(fn ($keyword) => Str::contains($haystack, $keyword));
            })
            ->values();
    }

    public function productsFor(RoomCollection $collection, int $perPage = 12): LengthAwarePaginator
    {
        $categoryIds = $this->categoriesFor($collection)->pluck('category_id');
        $query = $this->baseProductQuery();

        if ($categoryIds->isNotEmpty()) {
            $query->whereIn('category_id', $categoryIds);
        }

        $products = $query
            ->orderByDesc('amount_sold')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $products->setCollection($this->presenter->decorateProducts($products->getCollection()));

        return $products;
    }

    public function featuredProducts(RoomCollection $collection, int $limit = 8): Collection
    {
        return $this->presenter->decorateProducts(
            $this->productsQueryForCollection($collection)
                ->orderByDesc('amount_sold')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
        );
    }

    public function completeRoomProducts(RoomCollection $collection, int $limit = 4): Collection
    {
        return $this->featuredProducts($collection, 24)
            ->groupBy('category_id')
            ->map(fn ($group) => $group->sortByDesc(fn ($product) => $product->presentation->stock_total)->first())
            ->filter()
            ->take($limit)
            ->values();
    }

    public function relatedCollections(RoomCollection $collection): Collection
    {
        return RoomCollection::where('is_active', true)
            ->where('id', '<>', $collection->id)
            ->orderBy('title')
            ->limit(3)
            ->get();
    }

    public function heroImage(RoomCollection $collection, Collection $featuredProducts): string
    {
        if ($collection->hero_image) {
            return $collection->hero_image;
        }

        return $featuredProducts->first()?->presentation?->image ?: config('shop.seo.default_image');
    }

    private function productsQueryForCollection(RoomCollection $collection): Builder
    {
        $categoryIds = $this->categoriesFor($collection)->pluck('category_id');
        $query = $this->baseProductQuery();

        if ($categoryIds->isNotEmpty()) {
            $query->whereIn('category_id', $categoryIds);
        }

        return $query;
    }

    private function baseProductQuery(): Builder
    {
        return Product::query()
            ->with([
                'category',
                'brand',
                'approved_reviews',
                'detailed_products' => function ($query) {
                    $query->where('is_deleted', 0)
                        ->with('images', 'color', 'product_discounts.discount');
                },
            ])
            ->where('is_deleted', false)
            ->whereHas('detailed_products', fn ($query) => $query->where('is_deleted', 0));
    }
}
