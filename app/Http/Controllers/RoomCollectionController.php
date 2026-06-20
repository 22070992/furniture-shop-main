<?php

namespace App\Http\Controllers;

use App\Models\RoomCollection;
use App\Services\RoomCollectionService;
use App\Services\SeoService;

class RoomCollectionController extends Controller
{
    public function show(RoomCollection $collection, RoomCollectionService $collections, SeoService $seo)
    {
        abort_unless($collection->is_active, 404);

        $featuredProducts = $collections->featuredProducts($collection);
        $products = $collections->productsFor($collection);
        $categories = $collections->categoriesFor($collection);
        $completeRoomProducts = $collections->completeRoomProducts($collection);
        $relatedCollections = $collections->relatedCollections($collection);
        $heroImage = $collections->heroImage($collection, $featuredProducts);
        $collection->hero_image = $heroImage;

        return view('pages.collections.show', [
            'page' => $collection->title,
            'collection' => $collection,
            'categories' => $categories,
            'products' => $products,
            'featuredProducts' => $featuredProducts,
            'completeRoomProducts' => $completeRoomProducts,
            'relatedCollections' => $relatedCollections,
            'heroImage' => $heroImage,
            'seo' => $seo->collectionMeta($collection),
            'structuredData' => [
                $seo->breadcrumbSchema([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'Collections', 'url' => route('collections.index')],
                    ['name' => $collection->title, 'url' => $collection->publicUrl()],
                ]),
            ],
        ]);
    }

    public function index(SeoService $seo)
    {
        $collections = RoomCollection::where('is_active', true)->orderBy('title')->get();

        return view('pages.collections.index', [
            'page' => 'Room Collections',
            'collections' => $collections,
            'seo' => $seo->defaultMeta([
                'title' => 'Room Collections | Furemir',
                'description' => 'Shop furniture by room with curated living room, bedroom, dining room and office collections.',
                'canonical' => route('collections.index'),
            ]),
            'structuredData' => [
                $seo->breadcrumbSchema([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'Collections', 'url' => route('collections.index')],
                ]),
            ],
        ]);
    }
}
