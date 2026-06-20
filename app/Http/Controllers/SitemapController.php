<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RoomCollection;
use App\Services\SeoService;

class SitemapController extends Controller
{
    public function index(SeoService $seo)
    {
        return response($seo->sitemapIndexXml([
            route('sitemap.products'),
            route('sitemap.categories'),
            route('sitemap.collections'),
        ]), 200)->header('Content-Type', 'application/xml');
    }

    public function products(SeoService $seo)
    {
        $urls = Product::where('is_deleted', false)
            ->whereNotNull('slug')
            ->whereHas('detailed_products', fn ($query) => $query->where('is_deleted', 0))
            ->orderBy('product_id')
            ->get(['product_id', 'slug', 'updated_at'])
            ->map(fn (Product $product) => [
                'loc' => $product->publicUrl(),
                'lastmod' => $product->updated_at?->toDateString() ?? now()->toDateString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ]);

        return response($seo->sitemapXml($urls), 200)->header('Content-Type', 'application/xml');
    }

    public function categories(SeoService $seo)
    {
        $urls = Category::whereHas('products', fn ($query) => $query->where('is_deleted', false))
            ->orderBy('category_id')
            ->get(['category_id', 'updated_at'])
            ->map(fn (Category $category) => [
                'loc' => route('shop', ['category' => $category->category_id]),
                'lastmod' => $category->updated_at?->toDateString() ?? now()->toDateString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]);

        return response($seo->sitemapXml($urls), 200)->header('Content-Type', 'application/xml');
    }

    public function collections(SeoService $seo)
    {
        $urls = RoomCollection::where('is_active', true)
            ->orderBy('slug')
            ->get(['slug', 'updated_at'])
            ->map(fn (RoomCollection $collection) => [
                'loc' => $collection->publicUrl(),
                'lastmod' => $collection->updated_at?->toDateString() ?? now()->toDateString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ]);

        return response($seo->sitemapXml($urls), 200)->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /myorders',
            'Disallow: /my-feedback',
            'Sitemap: ' . route('sitemap.index'),
            '',
        ]);

        return response($content, 200)->header('Content-Type', 'text/plain');
    }
}
