<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\RoomCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeoService
{
    public function defaultMeta(array $overrides = []): array
    {
        $title = $overrides['title'] ?? config('shop.seo.default_title');
        $description = $overrides['description'] ?? config('shop.seo.default_description');
        $canonical = $overrides['canonical'] ?? url()->current();
        $image = $this->absoluteUrl($overrides['image'] ?? config('shop.seo.default_image'));

        return array_merge([
            'title' => $title,
            'description' => Str::limit(strip_tags((string) $description), 160, ''),
            'canonical' => $canonical,
            'robots' => $overrides['robots'] ?? 'index, follow',
            'og_type' => $overrides['og_type'] ?? 'website',
            'og_title' => $overrides['og_title'] ?? $title,
            'og_description' => $overrides['og_description'] ?? Str::limit(strip_tags((string) $description), 200, ''),
            'og_image' => $image,
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $overrides['twitter_title'] ?? $title,
            'twitter_description' => $overrides['twitter_description'] ?? Str::limit(strip_tags((string) $description), 200, ''),
            'twitter_image' => $image,
            'site_name' => config('shop.seo.site_name', 'Furemir'),
        ], $overrides);
    }

    public function productMeta(Product $product, array $reviewSummary = []): array
    {
        $description = $product->meta_description
            ?: $product->short_description
            ?: Str::limit(strip_tags((string) $product->description), 155, '');

        return $this->defaultMeta([
            'title' => $product->meta_title ?: Str::limit($product->name . ' | Furemir Furniture', 60, ''),
            'description' => $description ?: config('shop.seo.default_description'),
            'canonical' => $product->publicUrl(),
            'image' => $product->og_image ?: $this->productImage($product),
            'og_type' => 'product',
            'review_count' => $reviewSummary['count'] ?? 0,
        ]);
    }

    public function collectionMeta(RoomCollection $collection): array
    {
        return $this->defaultMeta([
            'title' => $collection->meta_title ?: $collection->title . ' Furniture | Furemir',
            'description' => $collection->meta_description ?: $collection->description ?: config('shop.seo.default_description'),
            'canonical' => $collection->publicUrl(),
            'image' => $collection->hero_image ?: config('shop.seo.default_image'),
        ]);
    }

    public function categoryMeta(Category $category): array
    {
        return $this->defaultMeta([
            'title' => $category->meta_title ?: $category->name . ' Furniture | Furemir',
            'description' => $category->meta_description ?: $category->description ?: config('shop.seo.default_description'),
            'canonical' => route('shop', ['category' => $category->category_id]),
        ]);
    }

    public function productSchema(Product $product, array $reviewSummary = []): array
    {
        $details = $product->detailed_products ?? collect();
        $prices = $details->pluck('original_price')->filter(fn ($price) => (float) $price > 0)->map(fn ($price) => (float) $price);
        $stock = (int) $details->sum('quantities');
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 300, ''),
            'image' => $this->productImages($product)->values()->all(),
            'sku' => $details->pluck('sku')->filter()->values()->all(),
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand?->name ?? config('shop.seo.organization_name', 'Furemir'),
            ],
            'category' => $product->category?->name,
            'url' => $product->publicUrl(),
        ];

        if ($prices->isNotEmpty()) {
            $schema['offers'] = [
                '@type' => $prices->count() > 1 ? 'AggregateOffer' : 'Offer',
                'priceCurrency' => 'VND',
                'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $product->publicUrl(),
            ];

            if ($prices->count() > 1) {
                $schema['offers']['lowPrice'] = (string) $prices->min();
                $schema['offers']['highPrice'] = (string) $prices->max();
                $schema['offers']['offerCount'] = $prices->count();
            } else {
                $schema['offers']['price'] = (string) $prices->first();
            }
        }

        if (($reviewSummary['count'] ?? 0) > 0 && ($reviewSummary['average'] ?? 0) > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $reviewSummary['average'],
                'reviewCount' => (int) $reviewSummary['count'],
            ];
        }

        return $schema;
    }

    public function breadcrumbSchema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    public function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('shop.seo.organization_name', 'Furemir'),
            'url' => url(config('shop.seo.organization_url', '/')),
            'logo' => $this->absoluteUrl(config('shop.seo.default_image')),
        ];
    }

    public function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('shop.seo.site_name', 'Furemir'),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url(config('shop.seo.search_path', '/shop?search={search_term_string}')),
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function sitemapXml(Collection $urls): string
    {
        $body = $urls->map(function (array $url) {
            return '  <url>'
                . '<loc>' . e($url['loc']) . '</loc>'
                . '<lastmod>' . e($url['lastmod']) . '</lastmod>'
                . '<changefreq>' . e($url['changefreq'] ?? 'weekly') . '</changefreq>'
                . '<priority>' . e($url['priority'] ?? '0.7') . '</priority>'
                . '</url>';
        })->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $body
            . "\n</urlset>\n";
    }

    public function sitemapIndexXml(array $sitemaps): string
    {
        $body = collect($sitemaps)->map(function (string $url) {
            return '  <sitemap><loc>' . e($url) . '</loc><lastmod>' . now()->toDateString() . '</lastmod></sitemap>';
        })->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $body
            . "\n</sitemapindex>\n";
    }

    public function absoluteUrl(?string $path): string
    {
        if (!$path) {
            return url(config('shop.seo.default_image'));
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return url($path);
    }

    public function productImage(Product $product): ?string
    {
        return $this->productImages($product)->first();
    }

    public function productImages(Product $product): Collection
    {
        return collect($product->detailed_products ?? [])
            ->flatMap(fn ($detail) => $detail->images ?? collect())
            ->pluck('url')
            ->filter()
            ->map(fn ($url) => $this->absoluteUrl($url))
            ->unique()
            ->values();
    }
}
