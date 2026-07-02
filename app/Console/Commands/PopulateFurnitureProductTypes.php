<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductDiscounts;
use App\Models\ProductImage;
use App\Models\ProductTag;
use App\Models\ProductType;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class PopulateFurnitureProductTypes extends Command
{
    protected $signature = 'products:populate-types {--report-only : Only print product type counts}';

    protected $description = 'Populate real furniture products so each product type has at least eight products.';

    private array $downloadedImages = [];
    private array $rejectedImages = [];
    private array $sourceLog = [];
    private array $candidateCache = [];
    private int $addedProducts = 0;
    private int $addedSkus = 0;

    public function handle(): int
    {
        $before = $this->typeCounts();

        if ($this->option('report-only')) {
            $this->printCounts('Current product type counts', $before);

            return self::SUCCESS;
        }

        $this->ensureBaseData();

        DB::transaction(function () {
            $discount = $this->discount();

            $this->correctExistingProductTypes();

            foreach ($this->catalog() as $item) {
                $this->seedProduct($item, $discount);
            }
        });

        $after = $this->typeCounts();
        $report = [
            'generated_at' => now()->toIso8601String(),
            'before' => $before,
            'after' => $after,
            'added_products' => $this->addedProducts,
            'added_skus' => $this->addedSkus,
            'downloaded_images' => $this->downloadedImages,
            'sources' => array_values($this->sourceLog),
            'rejected_images' => $this->rejectedImages,
        ];

        file_put_contents(storage_path('app/product_type_population_report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->printCounts('Product type counts before', $before);
        $this->printCounts('Product type counts after', $after);
        $this->info('Added products: ' . $this->addedProducts);
        $this->info('Added SKUs: ' . $this->addedSkus);
        $this->info('Downloaded images: ' . count($this->downloadedImages));
        $this->info('Rejected image candidates: ' . count($this->rejectedImages));
        $this->info('Report: ' . storage_path('app/product_type_population_report.json'));

        return self::SUCCESS;
    }

    private function seedProduct(array $item, Discount $discount): void
    {
        $type = ProductType::where('name', $item['type'])->firstOrFail();
        $category = Category::where('name', $item['category'])->firstOrFail();
        $brand = Brand::where('name', $item['brand'])->firstOrFail();
        $image = $this->imageFor($item);

        $product = Product::firstOrNew(['name' => $item['name']]);
        $exists = $product->exists;
        $product->fill([
            'description' => $item['description'],
            'short_description' => $item['short_description'],
            'material' => $item['material'],
            'category_id' => $category->category_id,
            'product_type_id' => $type->product_type_id,
            'brand_id' => $brand->brand_id,
            'amount_sold' => $product->amount_sold ?? $item['sold'],
            'is_deleted' => false,
            'meta_title' => Str::limit($item['name'] . ' | Furemir Furniture', 60, ''),
            'meta_description' => Str::limit($item['short_description'], 155, ''),
            'og_image' => $image['public_url'],
        ]);
        $product->save();

        if (!$exists) {
            $this->addedProducts++;
        }

        foreach ($item['tags'] as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName], ['name' => $tagName]);
            ProductTag::updateOrCreate(
                ['product_id' => $product->product_id, 'tag_id' => $tag->tag_id],
                ['product_id' => $product->product_id, 'tag_id' => $tag->tag_id]
            );
        }

        foreach ($item['variants'] as $index => $variant) {
            $color = Color::where('name', $variant['color'])->firstOrFail();
            $sku = $variant['sku'];
            $detail = ProductDetail::firstOrNew(['sku' => $sku]);
            $detailExists = $detail->exists;
            $detail->fill([
                'product_id' => $product->product_id,
                'name' => $item['name'] . ' - ' . $variant['color'],
                'description' => '<p>' . e($item['description']) . '</p><p>Variant color: ' . e($variant['color']) . '. Recommended for ' . e($item['use']) . '.</p>',
                'color_id' => $color->color_id,
                'size' => $item['size'],
                'original_price' => $variant['price'],
                'warranty_month' => $item['warranty'],
                'quantities' => $variant['stock'],
                'is_deleted' => false,
            ]);
            $detail->save();

            if (!$detailExists) {
                $this->addedSkus++;
            }

            ProductImage::updateOrCreate(
                ['sku' => $sku, 'url' => $image['public_url']],
                ['sku' => $sku, 'url' => $image['public_url'], 'alt_text' => $item['name'] . ' in ' . $variant['color']]
            );

            if ($index === 0 && ($item['discount'] ?? 0) > 0) {
                ProductDiscounts::updateOrCreate(
                    ['sku' => $sku, 'discount_id' => $discount->discount_id],
                    ['sku' => $sku, 'discount_id' => $discount->discount_id]
                );
            }
        }
    }

    private function imageFor(array $item): array
    {
        $directory = public_path('images/products');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $baseName = Str::slug($item['name']) . '-01';
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $existing = $directory . '/' . $baseName . '.' . $extension;
            if (is_file($existing) && $this->isValidImage($existing)) {
                return [
                    'public_url' => asset('images/products/' . basename($existing)),
                    'source_url' => 'existing local file',
                ];
            }
        }

        foreach ($item['image_sources'] ?? [] as $source) {
            $downloaded = $this->downloadCandidate($item, [
                'title' => $source['title'] ?? $item['name'],
                'url' => $source['download_url'] ?? $source['url'] ?? '',
                'page_url' => $source['page_url'] ?? null,
                'provider' => $source['provider'] ?? 'External image source',
            ], $baseName, $directory);

            if ($downloaded !== null) {
                return $downloaded;
            }
        }

        foreach ($this->imageQueriesFor($item) as $query) {
            foreach ($this->commonsImageCandidates($query) as $candidate) {
                if ($this->isRejectedTitle($candidate['title'] ?? '')) {
                    $this->reject($item['name'], $candidate, 'Rejected by title keyword');
                    continue;
                }

                $downloaded = $this->downloadCandidate($item, [
                    'title' => $candidate['title'] ?? '',
                    'url' => $candidate['url'] ?? '',
                    'page_url' => null,
                    'provider' => 'Wikimedia Commons',
                ], $baseName, $directory);

                if ($downloaded !== null) {
                    return $downloaded;
                }
            }
        }

        throw new \RuntimeException('No valid image found for ' . $item['name']);
    }

    private function downloadCandidate(array $item, array $candidate, string $baseName, string $directory): ?array
    {
        if (empty($candidate['url'])) {
            $this->reject($item['name'], $candidate, 'Missing source URL');

            return null;
        }

        if (isset($this->sourceLog[$candidate['url']])) {
            $this->reject($item['name'], $candidate, 'Source image already used by another new product');

            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'FuremirFurnitureSeeder/1.0'])
                ->get($candidate['url']);
        } catch (Throwable $exception) {
            $this->reject($item['name'], $candidate, 'HTTP error: ' . $exception->getMessage());

            return null;
        }

        if (!$response->ok()) {
            $this->reject($item['name'], $candidate, 'HTTP status ' . $response->status());

            return null;
        }

        $mime = strtolower((string) $response->header('Content-Type'));
        $extension = $this->extensionFromMime($mime);
        if ($extension === null) {
            $this->reject($item['name'], $candidate, 'Unsupported MIME ' . $mime);

            return null;
        }

        $path = $directory . '/' . $baseName . '.' . $extension;
        file_put_contents($path, $response->body());

        if (!$this->isValidImage($path)) {
            @unlink($path);
            $this->reject($item['name'], $candidate, 'Invalid raster image or dimensions below 600px');

            return null;
        }

        $publicUrl = asset('images/products/' . basename($path));
        $this->downloadedImages[] = $publicUrl;
        $this->sourceLog[$candidate['url']] = [
            'product' => $item['name'],
            'source_title' => $candidate['title'] ?? '',
            'source_url' => $candidate['url'],
            'source_page' => $candidate['page_url'] ?? null,
            'local_file' => $publicUrl,
            'provider' => $candidate['provider'] ?? 'External image source',
        ];

        return [
            'public_url' => $publicUrl,
            'source_url' => $candidate['url'],
        ];
    }

    private function commonsImageCandidates(string $query): array
    {
        if (array_key_exists($query, $this->candidateCache)) {
            return $this->candidateCache[$query];
        }

        $search = Http::timeout(20)
            ->retry(3, 1000)
            ->withHeaders(['User-Agent' => 'FuremirFurnitureSeeder/1.0'])
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $query,
                'srnamespace' => 6,
                'srlimit' => 20,
                'format' => 'json',
            ]);

        if (!$search->ok()) {
            $this->candidateCache[$query] = [];

            return [];
        }

        $titles = collect($search->json('query.search', []))
            ->pluck('title')
            ->filter()
            ->take(12)
            ->values();

        if ($titles->isEmpty()) {
            return [];
        }

        $info = Http::timeout(20)
            ->retry(3, 1000)
            ->withHeaders(['User-Agent' => 'FuremirFurnitureSeeder/1.0'])
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'titles' => $titles->implode('|'),
                'prop' => 'imageinfo',
                'iiprop' => 'url|mime|size',
                'format' => 'json',
            ]);

        if (!$info->ok()) {
            $this->candidateCache[$query] = [];

            return [];
        }

        $candidates = collect($info->json('query.pages', []))
            ->map(function ($page) {
                $imageInfo = $page['imageinfo'][0] ?? null;
                if (!$imageInfo) {
                    return null;
                }

                return [
                    'title' => $page['title'] ?? '',
                    'url' => $imageInfo['url'] ?? '',
                    'mime' => $imageInfo['mime'] ?? '',
                    'width' => $imageInfo['width'] ?? 0,
                    'height' => $imageInfo['height'] ?? 0,
                ];
            })
            ->filter(fn ($candidate) => $candidate && $candidate['url'])
            ->filter(fn ($candidate) => in_array(strtolower($candidate['mime']), ['image/jpeg', 'image/png', 'image/webp'], true))
            ->filter(fn ($candidate) => (int) $candidate['width'] >= 600 && (int) $candidate['height'] >= 600)
            ->values()
            ->all();

        $this->candidateCache[$query] = $candidates;

        return $candidates;
    }

    private function imageQueriesFor(array $item): array
    {
        return collect($item['image_queries'])
            ->merge([
                $item['type'],
                $item['type'] . ' furniture',
                $item['category'] . ' furniture',
                Str::before($item['type'], ' &'),
            ])
            ->map(fn ($query) => trim((string) $query))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isValidImage(string $path): bool
    {
        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }

        $mime = strtolower($info['mime'] ?? '');

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            && ($info[0] ?? 0) >= 600
            && ($info[1] ?? 0) >= 600;
    }

    private function extensionFromMime(string $mime): ?string
    {
        if (str_contains($mime, 'image/jpeg')) {
            return 'jpg';
        }
        if (str_contains($mime, 'image/png')) {
            return 'png';
        }
        if (str_contains($mime, 'image/webp')) {
            return 'webp';
        }

        return null;
    }

    private function reject(string $product, array $candidate, string $reason): void
    {
        $this->rejectedImages[] = [
            'product' => $product,
            'title' => $candidate['title'] ?? '',
            'url' => $candidate['url'] ?? '',
            'reason' => $reason,
        ];
    }

    private function isRejectedTitle(string $title): bool
    {
        $title = Str::lower($title);

        return Str::contains($title, [
            '.svg',
            '.tif',
            'logo',
            'icon',
            'map',
            'drawing',
            'design for',
            'plan',
            'painting',
            'portrait',
            'person',
            'people',
            'warehouse',
            'shop exterior',
            'storefront',
        ]);
    }

    private function ensureBaseData(): void
    {
        foreach ($this->requiredTags() as $name) {
            Tag::firstOrCreate(['name' => $name], ['name' => $name]);
        }
    }

    private function discount(): Discount
    {
        return Discount::updateOrCreate(
            ['title' => 'Catalog Expansion Deal'],
            [
                'description' => 'Selected new catalog items receive a launch discount.',
                'percentage' => 8,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'is_active' => true,
            ]
        );
    }

    private function correctExistingProductTypes(): void
    {
        $corrections = [
            'Grid Modular Shelf' => 'Cabinet & Storage',
            'Palma Rattan Sectional' => 'Outdoor Furniture',
            'Talon Office Credenza' => 'Cabinet & Storage',
            'Kira Drawer Unit' => 'Cabinet & Storage',
            'Cube House Organizer' => 'Cabinet & Storage',
        ];

        foreach ($corrections as $productName => $typeName) {
            $type = ProductType::where('name', $typeName)->first();
            if (!$type) {
                continue;
            }

            Product::where('name', $productName)->where('is_deleted', false)->update([
                'product_type_id' => $type->product_type_id,
            ]);
        }
    }

    private function typeCounts(): array
    {
        if (!Schema::hasTable('product_types')) {
            return [];
        }

        return ProductType::withCount(['products' => fn ($query) => $query->where('is_deleted', false)])
            ->orderBy('index')
            ->get()
            ->mapWithKeys(fn (ProductType $type) => [$type->name => $type->products_count])
            ->all();
    }

    private function printCounts(string $title, array $counts): void
    {
        $this->info($title);
        foreach ($counts as $type => $count) {
            $this->line('- ' . $type . ': ' . $count);
        }
    }

    private function requiredTags(): array
    {
        return [
            'Living Room',
            'Bedroom',
            'Dining',
            'Office',
            'Small Space',
            'Space Saving',
            'Relax Corner',
            'Natural Wood',
            'Upholstered',
            'Easy Storage',
            'Statement Piece',
            'Outdoor Living',
            'Minimalist',
            'Reading Corner',
            'Entryway',
        ];
    }

    private function variants(string $prefix, int $price, array $colors, int $stock = 12): array
    {
        return collect($colors)->map(function (string $color, int $index) use ($prefix, $price, $stock) {
            return [
                'sku' => $prefix . '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'color' => $color,
                'price' => $price + ($index * 180000),
                'stock' => max(6, $stock - ($index * 2)),
            ];
        })->all();
    }

    private function unsplashSource(string $photoId, string $title): array
    {
        return [
            'provider' => 'Unsplash',
            'title' => $title,
            'page_url' => 'https://unsplash.com/photos/' . $photoId,
            'download_url' => 'https://unsplash.com/photos/' . $photoId . '/download?force=true',
        ];
    }

    private function catalog(): array
    {
        return [
            [
                'name' => 'Selene Curved Boucle Sofa',
                'type' => 'Sofa',
                'category' => 'Living Room',
                'brand' => 'Furemir',
                'short_description' => 'Curved boucle sofa for soft, modern living rooms.',
                'description' => 'A rounded sofa with textured upholstery, supportive seat cushions and a quiet silhouette for relaxed living rooms.',
                'material' => 'Boucle fabric, foam, solid wood frame',
                'size' => '218 x 92 x 78 cm',
                'use' => 'living rooms, apartments and reading corners',
                'warranty' => 36,
                'sold' => 14,
                'discount' => 8,
                'tags' => ['Living Room', 'Upholstered', 'Statement Piece', 'Modern'],
                'variants' => $this->variants('EXP-SOFA-SELENE', 19200000, ['Ivory', 'Sand'], 14),
                'image_queries' => ['boucle sofa', 'curved sofa', 'white sofa living room'],
                'image_sources' => [$this->unsplashSource('KEGGJPKxcSc', 'Gray fabric sofa product photo')],
            ],
            [
                'name' => 'Marlow Walnut Frame Loveseat',
                'type' => 'Sofa',
                'category' => 'Living Room',
                'brand' => 'CasaOak',
                'short_description' => 'Compact loveseat with warm walnut arms.',
                'description' => 'A two-seat loveseat with a visible walnut frame, padded cushions and a compact footprint for small living spaces.',
                'material' => 'Walnut wood, linen blend, foam',
                'size' => '158 x 86 x 80 cm',
                'use' => 'small apartments and living room corners',
                'warranty' => 24,
                'sold' => 11,
                'discount' => 0,
                'tags' => ['Living Room', 'Small Space', 'Natural Wood', 'Compact'],
                'variants' => $this->variants('EXP-SOFA-MARLOW', 11800000, ['Walnut', 'Natural Oak'], 16),
                'image_queries' => ['loveseat sofa wood frame', 'small sofa furniture', 'two seat sofa'],
                'image_sources' => [$this->unsplashSource('4Co4l84sJWg', 'Brown leather couch product photo')],
            ],
            [
                'name' => 'Aria Compact Linen Sofa',
                'type' => 'Sofa',
                'category' => 'Living Room',
                'brand' => 'NordicNest',
                'short_description' => 'Clean linen sofa for small homes and studios.',
                'description' => 'A compact linen sofa with square cushions, slim arms and a neutral look that fits studio apartments.',
                'material' => 'Linen fabric, foam, pine frame',
                'size' => '176 x 84 x 79 cm',
                'use' => 'studio apartments and small living rooms',
                'warranty' => 24,
                'sold' => 9,
                'discount' => 8,
                'tags' => ['Living Room', 'Small Space', 'Upholstered', 'Scandinavian'],
                'variants' => $this->variants('EXP-SOFA-ARIA', 13200000, ['Beige', 'Gray'], 12),
                'image_queries' => ['linen sofa', 'grey sofa furniture', 'compact sofa'],
                'image_sources' => [$this->unsplashSource('4uD9w-pxBTA', 'Beige two-seat sofa product photo')],
            ],
            [
                'name' => 'Theo Low Profile Sofa',
                'type' => 'Sofa',
                'category' => 'Living Room',
                'brand' => 'StudioForm',
                'short_description' => 'Low modern sofa with wide relaxed seating.',
                'description' => 'A low-profile sofa designed for calm interiors, with deep seating and a broad back cushion.',
                'material' => 'Fabric upholstery, foam, black steel legs',
                'size' => '205 x 96 x 72 cm',
                'use' => 'modern living rooms and media areas',
                'warranty' => 30,
                'sold' => 10,
                'discount' => 0,
                'tags' => ['Living Room', 'Modern', 'Relax Corner'],
                'variants' => $this->variants('EXP-SOFA-THEO', 16900000, ['Charcoal', 'Navy'], 11),
                'image_queries' => ['modern sofa furniture', 'low sofa living room', 'blue sofa'],
                'image_sources' => [$this->unsplashSource('wF9NH87U-2E', 'Blue sofa in living room product photo')],
            ],
            [
                'name' => 'Brio Modular Corner Sofa',
                'type' => 'Sofa',
                'category' => 'Living Room',
                'brand' => 'Furemir',
                'short_description' => 'Modular corner sofa for family lounging.',
                'description' => 'A modular corner sofa with generous cushions that can anchor a family lounge or open-plan living space.',
                'material' => 'Performance fabric, foam, engineered wood',
                'size' => '260 x 180 x 82 cm',
                'use' => 'family living rooms and open spaces',
                'warranty' => 36,
                'sold' => 12,
                'discount' => 8,
                'tags' => ['Living Room', 'Family', 'Upholstered', 'Best Seller'],
                'variants' => $this->variants('EXP-SOFA-BRIO', 24800000, ['Ivory', 'Gray'], 10),
                'image_queries' => ['sectional sofa furniture', 'corner sofa living room', 'modular sofa'],
                'image_sources' => [$this->unsplashSource('dbH_vy7vICE', 'Orange sectional sofa product photo')],
            ],
            [
                'name' => 'Cyrus Deep Seat Sofa',
                'type' => 'Sofa',
                'category' => 'Living Room',
                'brand' => 'Furemir',
                'short_description' => 'Deep fabric sofa for relaxed family lounging.',
                'description' => 'A deep-seat sofa with broad cushions, low arms and a neutral shape for daily living room use.',
                'material' => 'Textured fabric, foam, solid wood frame',
                'size' => '210 x 96 x 80 cm',
                'use' => 'family living rooms and media corners',
                'warranty' => 36,
                'sold' => 10,
                'discount' => 0,
                'tags' => ['Living Room', 'Family', 'Upholstered'],
                'variants' => $this->variants('EXP-SOFA-CYRUS', 17600000, ['Gray', 'Charcoal'], 12),
                'image_queries' => ['deep seat sofa furniture', 'gray sofa furniture', 'family sofa'],
                'image_sources' => [$this->unsplashSource('2dcYhvbHV-M', 'Gray sofa in living room product photo')],
            ],
            [
                'name' => 'Kira Oak Platform Bed',
                'type' => 'Bed',
                'category' => 'Bedroom',
                'brand' => 'CasaOak',
                'short_description' => 'Low oak platform bed for calm bedrooms.',
                'description' => 'A solid oak platform bed with clean rails, low proportions and a warm natural finish.',
                'material' => 'Solid oak and plywood slats',
                'size' => '170 x 214 x 88 cm',
                'use' => 'master bedrooms and minimalist rooms',
                'warranty' => 36,
                'sold' => 13,
                'discount' => 8,
                'tags' => ['Bedroom', 'Natural Wood', 'Minimalist'],
                'variants' => $this->variants('EXP-BED-KIRA', 15800000, ['Natural Oak', 'Walnut'], 12),
                'image_queries' => ['wooden platform bed', 'oak bed furniture', 'bedroom bed furniture'],
                'image_sources' => [$this->unsplashSource('HD7QBx2Yfa4', 'Wood floor bedroom with upholstered bed')],
            ],
            [
                'name' => 'Haven Tufted Storage Bed',
                'type' => 'Bed',
                'category' => 'Bedroom',
                'brand' => 'Furemir',
                'short_description' => 'Upholstered bed with hidden storage space.',
                'description' => 'A tufted upholstered bed with under-bed storage for bedding, seasonal clothing and extra pillows.',
                'material' => 'Fabric upholstery, hydraulic storage base, foam',
                'size' => '172 x 218 x 112 cm',
                'use' => 'bedrooms that need extra storage',
                'warranty' => 36,
                'sold' => 8,
                'discount' => 8,
                'tags' => ['Bedroom', 'Easy Storage', 'Upholstered'],
                'variants' => $this->variants('EXP-BED-HAVEN', 18200000, ['Sand', 'Gray'], 9),
                'image_queries' => ['upholstered storage bed', 'tufted bed furniture', 'fabric bed'],
                'image_sources' => [$this->unsplashSource('iAftdIcgpFc', 'Tufted bed with bench in bedroom')],
            ],
            [
                'name' => 'Milo Rattan Daybed',
                'type' => 'Bed',
                'category' => 'Bedroom',
                'brand' => 'UrbanWeave',
                'short_description' => 'Rattan daybed for guest rooms and lounging.',
                'description' => 'A woven rattan daybed that works as a guest bed, reading couch or relaxed bedroom accent.',
                'material' => 'Rattan, solid wood, cotton cushion',
                'size' => '198 x 92 x 76 cm',
                'use' => 'guest rooms, sunrooms and reading corners',
                'warranty' => 18,
                'sold' => 7,
                'discount' => 0,
                'tags' => ['Bedroom', 'Natural', 'Relax Corner', 'Reading Corner'],
                'variants' => $this->variants('EXP-BED-MILO', 10900000, ['Rattan Natural', 'Ivory'], 10),
                'image_queries' => ['rattan daybed furniture', 'daybed furniture', 'rattan bed'],
                'image_sources' => [$this->unsplashSource('MKNbt8Tapu4', 'White bed in blue bedroom')],
            ],
            [
                'name' => 'Sienna Solid Wood Bed',
                'type' => 'Bed',
                'category' => 'Bedroom',
                'brand' => 'CasaOak',
                'short_description' => 'Solid wood bed with timeless proportions.',
                'description' => 'A sturdy solid wood bed with a raised headboard and balanced proportions for long-term bedroom use.',
                'material' => 'Solid wood, veneer, plywood slats',
                'size' => '168 x 212 x 105 cm',
                'use' => 'family bedrooms and natural interiors',
                'warranty' => 42,
                'sold' => 9,
                'discount' => 0,
                'tags' => ['Bedroom', 'Natural Wood', 'Family'],
                'variants' => $this->variants('EXP-BED-SIENNA', 17100000, ['Walnut', 'Natural Oak'], 8),
                'image_queries' => ['solid wood bed furniture', 'wood bed frame', 'walnut bed'],
                'image_sources' => [$this->unsplashSource('aRT5UCf2MYY', 'Bed with dark wooden headboard')],
            ],
            [
                'name' => 'Oslo Upholstered Single Bed',
                'type' => 'Bed',
                'category' => 'Bedroom',
                'brand' => 'NordicNest',
                'short_description' => 'Soft single bed for compact bedrooms.',
                'description' => 'A compact upholstered single bed with a rounded headboard for small bedrooms and guest rooms.',
                'material' => 'Fabric upholstery, foam, plywood slats',
                'size' => '105 x 205 x 92 cm',
                'use' => 'small bedrooms and guest rooms',
                'warranty' => 24,
                'sold' => 6,
                'discount' => 8,
                'tags' => ['Bedroom', 'Small Space', 'Upholstered'],
                'variants' => $this->variants('EXP-BED-OSLO', 8200000, ['Beige', 'Gray'], 12),
                'image_queries' => ['single upholstered bed', 'small bed furniture', 'single bed'],
                'image_sources' => [$this->unsplashSource('nclN_J0UtJ8', 'Tufted upholstered bed close view')],
            ],
            [
                'name' => 'Fenn Sliding Door Wardrobe',
                'type' => 'Wardrobe',
                'category' => 'Bedroom',
                'brand' => 'StudioForm',
                'short_description' => 'Sliding wardrobe for rooms with limited clearance.',
                'description' => 'A two-door sliding wardrobe with hanging space, shelves and a clean flat front.',
                'material' => 'Engineered wood, laminate, aluminum rail',
                'size' => '180 x 62 x 210 cm',
                'use' => 'bedrooms with narrow walkways',
                'warranty' => 36,
                'sold' => 6,
                'discount' => 8,
                'tags' => ['Bedroom', 'Easy Storage', 'Space Saving'],
                'variants' => $this->variants('EXP-WARD-FENN', 18900000, ['Ivory', 'Walnut'], 8),
                'image_queries' => ['sliding wardrobe furniture', 'wardrobe closet furniture', 'white wardrobe'],
                'image_sources' => [$this->unsplashSource('Og7lgJjwESM', 'Modern bedroom with large white wardrobe')],
            ],
            [
                'name' => 'Orin Two Door Oak Wardrobe',
                'type' => 'Wardrobe',
                'category' => 'Bedroom',
                'brand' => 'CasaOak',
                'short_description' => 'Oak wardrobe with classic double doors.',
                'description' => 'A two-door oak wardrobe with a hanging rail and lower drawer for everyday clothing storage.',
                'material' => 'Oak veneer, engineered wood, metal handles',
                'size' => '100 x 58 x 198 cm',
                'use' => 'bedrooms, guest rooms and rental apartments',
                'warranty' => 30,
                'sold' => 7,
                'discount' => 0,
                'tags' => ['Bedroom', 'Natural Wood', 'Easy Storage'],
                'variants' => $this->variants('EXP-WARD-ORIN', 11600000, ['Natural Oak', 'Walnut'], 11),
                'image_queries' => ['oak wardrobe furniture', 'wood wardrobe closet', 'two door wardrobe'],
                'image_sources' => [$this->unsplashSource('mi6-Sl4eGY0', 'Brown and white wooden wardrobe cabinet')],
            ],
            [
                'name' => 'Livia Mirrored Wardrobe',
                'type' => 'Wardrobe',
                'category' => 'Bedroom',
                'brand' => 'Furemir',
                'short_description' => 'Mirrored wardrobe for bright, practical bedrooms.',
                'description' => 'A mirrored wardrobe that combines full-length reflection with hidden rails and shelves.',
                'material' => 'Engineered wood, mirror glass, metal hardware',
                'size' => '160 x 60 x 210 cm',
                'use' => 'bedrooms needing mirror and storage in one piece',
                'warranty' => 36,
                'sold' => 8,
                'discount' => 8,
                'tags' => ['Bedroom', 'Easy Storage', 'Space Saving'],
                'variants' => $this->variants('EXP-WARD-LIVIA', 17600000, ['White', 'Gray'], 9),
                'image_queries' => ['mirrored wardrobe furniture', 'mirror wardrobe closet', 'bedroom wardrobe'],
                'image_sources' => [$this->unsplashSource('-l6QV6ifHLI', 'Walk-in closet with mirror and storage')],
            ],
            [
                'name' => 'Tara Compact Wardrobe',
                'type' => 'Wardrobe',
                'category' => 'Bedroom',
                'brand' => 'UrbanWeave',
                'short_description' => 'Compact wardrobe for studio rooms.',
                'description' => 'A narrow wardrobe with one hanging compartment and shelves for small-room organization.',
                'material' => 'Melamine board, metal rail, ABS edge band',
                'size' => '80 x 54 x 190 cm',
                'use' => 'studio apartments and children bedrooms',
                'warranty' => 18,
                'sold' => 6,
                'discount' => 0,
                'tags' => ['Bedroom', 'Small Space', 'Easy Storage'],
                'variants' => $this->variants('EXP-WARD-TARA', 7900000, ['White', 'Natural Oak'], 13),
                'image_queries' => ['compact wardrobe furniture', 'small wardrobe closet', 'white wardrobe furniture'],
                'image_sources' => [$this->unsplashSource('l8rvtvzhgH8', 'White closet with drawers and coat rack')],
            ],
            [
                'name' => 'Camden Tall Wardrobe',
                'type' => 'Wardrobe',
                'category' => 'Storage',
                'brand' => 'StudioForm',
                'short_description' => 'Tall wardrobe with organized interior sections.',
                'description' => 'A tall wardrobe with divided shelving, a hanging rail and lower drawers for seasonal storage.',
                'material' => 'Engineered wood, laminate, steel rail',
                'size' => '120 x 58 x 220 cm',
                'use' => 'bedrooms and storage rooms',
                'warranty' => 36,
                'sold' => 7,
                'discount' => 0,
                'tags' => ['Storage', 'Easy Storage', 'Bedroom'],
                'variants' => $this->variants('EXP-WARD-CAMDEN', 13900000, ['Charcoal', 'Walnut'], 10),
                'image_queries' => ['tall wardrobe furniture', 'wood closet wardrobe', 'wardrobe cabinet'],
                'image_sources' => [$this->unsplashSource('dO9rp6DxreY', 'Walk-in closet filled with clothes')],
            ],
            [
                'name' => 'Nova Open Wardrobe',
                'type' => 'Wardrobe',
                'category' => 'Storage',
                'brand' => 'NordicNest',
                'short_description' => 'Open wardrobe for capsule clothing display.',
                'description' => 'An open wardrobe with shelves and a hanging rail for easy-access clothing organization.',
                'material' => 'Oak veneer, powder-coated steel',
                'size' => '110 x 45 x 180 cm',
                'use' => 'walk-in corners and compact dressing areas',
                'warranty' => 24,
                'sold' => 5,
                'discount' => 8,
                'tags' => ['Storage', 'Small Space', 'Natural Wood'],
                'variants' => $this->variants('EXP-WARD-NOVA', 9200000, ['Natural Oak', 'Black'], 10),
                'image_queries' => ['open wardrobe furniture', 'clothes rack wardrobe', 'open closet furniture'],
                'image_sources' => [$this->unsplashSource('oqRdi1GNYp0', 'White closet with clothes hanging on a rail')],
            ],
            [
                'name' => 'Vale Oak Writing Desk',
                'type' => 'Desk',
                'category' => 'Home Office',
                'brand' => 'CasaOak',
                'short_description' => 'Oak writing desk with a slim work surface.',
                'description' => 'A clean writing desk with a solid oak look, slim legs and enough space for laptop work.',
                'material' => 'Oak veneer, solid wood legs',
                'size' => '120 x 58 x 76 cm',
                'use' => 'home offices, bedrooms and study corners',
                'warranty' => 24,
                'sold' => 10,
                'discount' => 8,
                'tags' => ['Office', 'Natural Wood', 'Small Space'],
                'variants' => $this->variants('EXP-DESK-VALE', 6200000, ['Natural Oak', 'Walnut'], 15),
                'image_queries' => ['wood writing desk furniture', 'oak desk furniture', 'home office desk'],
                'image_sources' => [$this->unsplashSource('wJ7yGwz2-00', 'Wooden writing desk with chair')],
            ],
            [
                'name' => 'Mercer Adjustable Standing Desk',
                'type' => 'Desk',
                'category' => 'Home Office',
                'brand' => 'StudioForm',
                'short_description' => 'Height-adjustable desk for flexible working.',
                'description' => 'An adjustable standing desk with a wide top and stable steel frame for sit-stand work routines.',
                'material' => 'Engineered wood top, powder-coated steel frame',
                'size' => '140 x 70 x 72-118 cm',
                'use' => 'home offices and hybrid work setups',
                'warranty' => 36,
                'sold' => 9,
                'discount' => 0,
                'tags' => ['Office', 'Work-from-home', 'Modern'],
                'variants' => $this->variants('EXP-DESK-MERCER', 11200000, ['Black', 'White'], 12),
                'image_queries' => ['standing desk furniture', 'adjustable desk furniture', 'modern office desk'],
                'image_sources' => [$this->unsplashSource('tdnYk4qOGhc', 'Adjustable office desk in home office')],
            ],
            [
                'name' => 'Ryo Compact Study Desk',
                'type' => 'Desk',
                'category' => 'Home Office',
                'brand' => 'NordicNest',
                'short_description' => 'Compact study desk with simple storage.',
                'description' => 'A small study desk with an open shelf and clean lines for bedrooms or student rooms.',
                'material' => 'Engineered wood, oak veneer',
                'size' => '95 x 50 x 75 cm',
                'use' => 'student rooms and small work corners',
                'warranty' => 18,
                'sold' => 8,
                'discount' => 8,
                'tags' => ['Office', 'Small Space', 'Space Saving'],
                'variants' => $this->variants('EXP-DESK-RYO', 4200000, ['Natural Oak', 'White'], 18),
                'image_queries' => ['small study desk furniture', 'compact desk', 'student desk furniture'],
                'image_sources' => [$this->unsplashSource('lB9ylP8e9Sg', 'Compact desk with chair and task lamp')],
            ],
            [
                'name' => 'Aster Drawer Work Desk',
                'type' => 'Desk',
                'category' => 'Home Office',
                'brand' => 'Furemir',
                'short_description' => 'Work desk with drawers for daily supplies.',
                'description' => 'A practical work desk with side drawers, cable-friendly surface and a calm home-office profile.',
                'material' => 'Engineered wood, metal runners',
                'size' => '130 x 60 x 76 cm',
                'use' => 'daily home office work and study',
                'warranty' => 24,
                'sold' => 7,
                'discount' => 0,
                'tags' => ['Office', 'Easy Storage', 'Work-from-home'],
                'variants' => $this->variants('EXP-DESK-ASTER', 7600000, ['Walnut', 'Charcoal'], 11),
                'image_queries' => ['desk with drawers furniture', 'office desk drawer', 'work desk furniture'],
                'image_sources' => [$this->unsplashSource('1f_lBHJvY7c', 'Home office desk with shelving')],
            ],
            [
                'name' => 'Harlow Upholstered Bench',
                'type' => 'Bench',
                'category' => 'Bedroom',
                'brand' => 'UrbanWeave',
                'short_description' => 'Soft upholstered bench for the end of a bed.',
                'description' => 'A padded bench with slim legs, designed for bedroom seating and daily dressing routines.',
                'material' => 'Fabric upholstery, foam, steel legs',
                'size' => '120 x 42 x 46 cm',
                'use' => 'bedroom footboards and dressing corners',
                'warranty' => 18,
                'sold' => 8,
                'discount' => 8,
                'tags' => ['Bedroom', 'Upholstered', 'Small Space'],
                'variants' => $this->variants('EXP-BENCH-HARLOW', 4800000, ['Beige', 'Gray'], 14),
                'image_queries' => ['upholstered bench furniture', 'bedroom bench furniture', 'fabric bench'],
                'image_sources' => [$this->unsplashSource('5gYM1p41ryo', 'Yellow upholstered bench')],
            ],
            [
                'name' => 'Cedar Entryway Storage Bench',
                'type' => 'Bench',
                'category' => 'Storage',
                'brand' => 'CasaOak',
                'short_description' => 'Entry bench with hidden shoe storage.',
                'description' => 'A wooden entryway bench with a lift-up seat for shoes, bags and seasonal accessories.',
                'material' => 'Cedar wood, veneer, metal hinges',
                'size' => '110 x 40 x 48 cm',
                'use' => 'entryways, hallways and mudrooms',
                'warranty' => 24,
                'sold' => 9,
                'discount' => 0,
                'tags' => ['Entryway', 'Easy Storage', 'Natural Wood'],
                'variants' => $this->variants('EXP-BENCH-CEDAR', 5600000, ['Natural Oak', 'Walnut'], 12),
                'image_queries' => ['storage bench furniture', 'entryway bench', 'wood storage bench'],
                'image_sources' => [$this->unsplashSource('YiTnnioRKic', 'Wooden bench in entryway room')],
            ],
            [
                'name' => 'Oslo Dining Bench',
                'type' => 'Bench',
                'category' => 'Dining Room',
                'brand' => 'NordicNest',
                'short_description' => 'Simple dining bench for relaxed table seating.',
                'description' => 'A clean dining bench that pairs with rectangular dining tables and saves visual space.',
                'material' => 'Solid wood, clear lacquer',
                'size' => '140 x 36 x 45 cm',
                'use' => 'dining rooms and breakfast corners',
                'warranty' => 24,
                'sold' => 10,
                'discount' => 8,
                'tags' => ['Dining', 'Natural Wood', 'Scandinavian'],
                'variants' => $this->variants('EXP-BENCH-OSLO', 4300000, ['Natural Oak', 'Black'], 13),
                'image_queries' => ['dining bench furniture', 'wood dining bench', 'table bench furniture'],
                'image_sources' => [$this->unsplashSource('797UsFjOSC0', 'Dining room table with bench')],
            ],
            [
                'name' => 'Terra Outdoor Garden Bench',
                'type' => 'Bench',
                'category' => 'Outdoor',
                'brand' => 'PatioHaven',
                'short_description' => 'Weather-ready bench for balconies and gardens.',
                'description' => 'A garden bench with a durable slatted seat and outdoor-friendly finish.',
                'material' => 'Acacia wood, weather-resistant oil finish',
                'size' => '130 x 58 x 86 cm',
                'use' => 'gardens, balconies and patios',
                'warranty' => 18,
                'sold' => 8,
                'discount' => 0,
                'tags' => ['Outdoor', 'Outdoor Living', 'Natural Wood'],
                'variants' => $this->variants('EXP-BENCH-TERRA', 5200000, ['Natural Oak', 'Brown'], 10),
                'image_queries' => ['garden bench furniture', 'outdoor bench furniture', 'wood garden bench'],
                'image_sources' => [$this->unsplashSource('ka3Gq_CYz-0', 'Wooden entryway bench with pillows')],
            ],
            [
                'name' => 'Marin Patio Lounge Set',
                'type' => 'Outdoor Furniture',
                'category' => 'Outdoor',
                'brand' => 'PatioHaven',
                'short_description' => 'Outdoor lounge set for relaxed patio seating.',
                'description' => 'A patio lounge set with cushioned seating and a low table for outdoor entertaining.',
                'material' => 'Powder-coated aluminum, outdoor fabric',
                'size' => '220 x 170 x 72 cm',
                'use' => 'patios, terraces and garden lounges',
                'warranty' => 24,
                'sold' => 7,
                'discount' => 8,
                'tags' => ['Outdoor', 'Outdoor Living', 'Family'],
                'variants' => $this->variants('EXP-OUT-MARIN', 21800000, ['Gray', 'Navy'], 8),
                'image_queries' => ['outdoor lounge furniture', 'patio lounge set', 'garden sofa furniture'],
                'image_sources' => [$this->unsplashSource('jUSmFN-d1bM', 'Outdoor patio seating with hammock and dining area')],
            ],
            [
                'name' => 'Luma Folding Patio Chair',
                'type' => 'Outdoor Furniture',
                'category' => 'Outdoor',
                'brand' => 'PatioHaven',
                'short_description' => 'Foldable patio chair for flexible outdoor seating.',
                'description' => 'A folding patio chair with slatted seat and compact storage for balconies and small yards.',
                'material' => 'Acacia wood, stainless steel hardware',
                'size' => '48 x 58 x 86 cm',
                'use' => 'balconies, patios and picnic corners',
                'warranty' => 12,
                'sold' => 12,
                'discount' => 0,
                'tags' => ['Outdoor', 'Space Saving', 'Outdoor Living'],
                'variants' => $this->variants('EXP-OUT-LUMA', 1800000, ['Natural Oak', 'Brown'], 20),
                'image_queries' => ['folding patio chair furniture', 'outdoor folding chair', 'garden chair furniture'],
                'image_sources' => [$this->unsplashSource('C6YxBoFBs4k', 'Patio chair set on balcony')],
            ],
            [
                'name' => 'Cove Teak Garden Table',
                'type' => 'Outdoor Furniture',
                'category' => 'Outdoor',
                'brand' => 'CasaOak',
                'short_description' => 'Teak garden table for outdoor meals.',
                'description' => 'A teak garden table with a slatted top, sized for casual outdoor meals and drinks.',
                'material' => 'Teak wood, outdoor oil finish',
                'size' => '150 x 80 x 74 cm',
                'use' => 'garden dining and patio meals',
                'warranty' => 24,
                'sold' => 6,
                'discount' => 8,
                'tags' => ['Outdoor', 'Dining', 'Natural Wood'],
                'variants' => $this->variants('EXP-OUT-COVE', 8900000, ['Natural Oak', 'Brown'], 9),
                'image_queries' => ['teak garden table furniture', 'outdoor table furniture', 'patio dining table'],
                'image_sources' => [$this->unsplashSource('Pav8_i4Rylc', 'Patio dining table surrounded by chairs')],
            ],
            [
                'name' => 'Nusa Rattan Outdoor Sofa',
                'type' => 'Outdoor Furniture',
                'category' => 'Outdoor',
                'brand' => 'UrbanWeave',
                'short_description' => 'Rattan outdoor sofa with loose cushions.',
                'description' => 'A woven outdoor sofa with removable cushions and a relaxed tropical profile.',
                'material' => 'Synthetic rattan, outdoor fabric, steel frame',
                'size' => '178 x 82 x 78 cm',
                'use' => 'covered balconies and patio lounges',
                'warranty' => 18,
                'sold' => 8,
                'discount' => 0,
                'tags' => ['Outdoor', 'Outdoor Living', 'Relax Corner'],
                'variants' => $this->variants('EXP-OUT-NUSA', 12600000, ['Rattan Natural', 'Gray'], 10),
                'image_queries' => ['rattan outdoor sofa furniture', 'wicker patio sofa', 'outdoor sofa furniture'],
                'image_sources' => [$this->unsplashSource('d060XQVXTNo', 'Outdoor living space with sofas and hanging chairs')],
            ],
            [
                'name' => 'Alder Balcony Bistro Set',
                'type' => 'Outdoor Furniture',
                'category' => 'Outdoor',
                'brand' => 'PatioHaven',
                'short_description' => 'Compact bistro set for small balconies.',
                'description' => 'A small outdoor bistro set with a round table and two chairs for morning coffee.',
                'material' => 'Powder-coated steel, wood slats',
                'size' => 'Table 60 x 60 x 72 cm; chairs 45 x 50 x 82 cm',
                'use' => 'small balconies and outdoor breakfast corners',
                'warranty' => 18,
                'sold' => 9,
                'discount' => 8,
                'tags' => ['Outdoor', 'Small Space', 'Dining'],
                'variants' => $this->variants('EXP-OUT-ALDER', 6400000, ['Black', 'Natural Oak'], 12),
                'image_queries' => ['balcony bistro set furniture', 'outdoor bistro set', 'patio bistro furniture'],
                'image_sources' => [$this->unsplashSource('UQXMWJHusQs', 'Outdoor patio seating with umbrella and plants')],
            ],
            [
                'name' => 'Alba Ceramic Table Lamp',
                'type' => 'Decor & Accessories',
                'category' => 'Living Room',
                'brand' => 'StudioForm',
                'short_description' => 'Ceramic table lamp for warm accent lighting.',
                'description' => 'A ceramic table lamp with a fabric shade, designed for side tables, consoles and bedside cabinets.',
                'material' => 'Ceramic base, fabric shade, metal fitting',
                'size' => '32 x 32 x 52 cm',
                'use' => 'side tables, nightstands and console tables',
                'warranty' => 12,
                'sold' => 11,
                'discount' => 8,
                'tags' => ['Living Room', 'Bedroom', 'Reading Corner'],
                'variants' => $this->variants('EXP-DECOR-ALBA', 2100000, ['Ivory', 'Terracotta'], 18),
                'image_queries' => ['ceramic table lamp furniture', 'table lamp shade', 'bedside table lamp'],
                'image_sources' => [$this->unsplashSource('723JFDBPM1c', 'Table with lamp and vase')],
            ],
            [
                'name' => 'Mira Woven Floor Lamp',
                'type' => 'Decor & Accessories',
                'category' => 'Living Room',
                'brand' => 'UrbanWeave',
                'short_description' => 'Woven floor lamp for relaxed ambient light.',
                'description' => 'A woven floor lamp that adds texture and soft ambient lighting to lounge corners.',
                'material' => 'Rattan shade, steel stand',
                'size' => '42 x 42 x 148 cm',
                'use' => 'living room corners and reading areas',
                'warranty' => 12,
                'sold' => 8,
                'discount' => 0,
                'tags' => ['Living Room', 'Relax Corner', 'Natural'],
                'variants' => $this->variants('EXP-DECOR-MIRA', 3400000, ['Rattan Natural', 'Black'], 14),
                'image_queries' => ['woven floor lamp furniture', 'rattan floor lamp', 'floor lamp furniture'],
                'image_sources' => [$this->unsplashSource('kQdH0Aal8ts', 'Floor lamps in living room decor')],
            ],
            [
                'name' => 'Nola Round Wall Mirror',
                'type' => 'Decor & Accessories',
                'category' => 'Bedroom',
                'brand' => 'Furemir',
                'short_description' => 'Round wall mirror with a slim wooden frame.',
                'description' => 'A round wall mirror with a slim frame for entryways, bedrooms and vanity corners.',
                'material' => 'Mirror glass, wood frame',
                'size' => '80 x 4 x 80 cm',
                'use' => 'entryways, bedrooms and dressing corners',
                'warranty' => 12,
                'sold' => 9,
                'discount' => 8,
                'tags' => ['Bedroom', 'Entryway', 'Natural Wood'],
                'variants' => $this->variants('EXP-DECOR-NOLA', 2800000, ['Natural Oak', 'Black'], 16),
                'image_queries' => ['round wall mirror furniture', 'wood frame mirror', 'decorative wall mirror'],
                'image_sources' => [$this->unsplashSource('dAZ8sazVOpw', 'Round wall mirror with armchair')],
            ],
            [
                'name' => 'Vega Ribbed Ceramic Vase',
                'type' => 'Decor & Accessories',
                'category' => 'Living Room',
                'brand' => 'StudioForm',
                'short_description' => 'Ribbed ceramic vase for shelves and console tops.',
                'description' => 'A decorative ceramic vase with a subtle ribbed surface for dried flowers, shelves and console styling.',
                'material' => 'Glazed ceramic',
                'size' => '22 x 22 x 38 cm',
                'use' => 'console tables, shelves and dining sideboards',
                'warranty' => 6,
                'sold' => 10,
                'discount' => 0,
                'tags' => ['Living Room', 'Statement Piece', 'Modern'],
                'variants' => $this->variants('EXP-DECOR-VEGA', 1250000, ['Ivory', 'Sage'], 20),
                'image_queries' => ['ceramic vase home decor', 'decorative vase furniture', 'vase shelf decor'],
                'image_sources' => [$this->unsplashSource('SElPc-8YxbQ', 'Decorative ceramic vases')],
            ],
            [
                'name' => 'Liora Shelf Vase Set',
                'type' => 'Decor & Accessories',
                'category' => 'Living Room',
                'brand' => 'NordicNest',
                'short_description' => 'Small ceramic vase set for layered shelf styling.',
                'description' => 'A set of small ceramic vases designed to add color and scale to open shelves, sideboards and display cabinets.',
                'material' => 'Ceramic with matte glaze',
                'size' => 'Set of 3; tallest 24 cm',
                'use' => 'bookshelves, sideboards and display cabinets',
                'warranty' => 6,
                'sold' => 7,
                'discount' => 8,
                'tags' => ['Living Room', 'Small Space', 'Scandinavian'],
                'variants' => $this->variants('EXP-DECOR-LIORA', 980000, ['Terracotta', 'Sage'], 24),
                'image_queries' => ['vase set shelf decor', 'ceramic vases home decor', 'decorative shelf vases'],
                'image_sources' => [$this->unsplashSource('q0EiejULK8Y', 'Colorful ceramic vases on shelf')],
            ],
            [
                'name' => 'Solis Sculpted Table Lamp',
                'type' => 'Decor & Accessories',
                'category' => 'Bedroom',
                'brand' => 'Furemir',
                'short_description' => 'Sculpted table lamp for warm bedroom lighting.',
                'description' => 'A compact table lamp with a sculpted base and soft shade for nightstands, desks and reading corners.',
                'material' => 'Ceramic base, linen shade, brass fitting',
                'size' => '30 x 30 x 48 cm',
                'use' => 'nightstands, desks and reading corners',
                'warranty' => 12,
                'sold' => 8,
                'discount' => 0,
                'tags' => ['Bedroom', 'Reading Corner', 'Statement Piece'],
                'variants' => $this->variants('EXP-DECOR-SOLIS', 1850000, ['Ivory', 'Sand'], 18),
                'image_queries' => ['table lamp home decor', 'bedside lamp decor', 'decorative lamp'],
                'image_sources' => [$this->unsplashSource('sK2B-ec1-go', 'Ornate lamp on table with sunlight')],
            ],
            [
                'name' => 'Oriel Framed Art Set',
                'type' => 'Decor & Accessories',
                'category' => 'Living Room',
                'brand' => 'UrbanWeave',
                'short_description' => 'Framed art set for console and shelf displays.',
                'description' => 'A coordinated framed art set for layering on console tables, shelves and living room accent walls.',
                'material' => 'Wood frame, glass, printed art paper',
                'size' => 'Set of 2; 40 x 50 cm and 30 x 40 cm',
                'use' => 'living rooms, entryways and console tables',
                'warranty' => 6,
                'sold' => 9,
                'discount' => 8,
                'tags' => ['Living Room', 'Entryway', 'Statement Piece'],
                'variants' => $this->variants('EXP-DECOR-ORIEL', 1650000, ['Natural Oak', 'Black'], 22),
                'image_queries' => ['framed art home decor', 'console table art lamp', 'wall art decor'],
                'image_sources' => [$this->unsplashSource('2nYWFdViU2s', 'Lamp and framed artwork on console table')],
            ],
        ];
    }
}
