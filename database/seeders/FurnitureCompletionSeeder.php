<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductImage;
use App\Models\ProductTag;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FurnitureCompletionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $colors = $this->seedColors();
            $brands = $this->seedBrands();
            $tags = $this->seedTags();

            $this->seedFeaturedChairCategories($colors, $brands, $tags);

            Product::with(['category', 'detailed_products.color'])
                ->where('is_deleted', false)
                ->orderBy('product_id')
                ->get()
                ->each(function (Product $product) use ($colors, $tags) {
                    $this->completeProduct($product, $colors, $tags);
                });
        });
    }

    private function seedColors()
    {
        return collect([
            ['name' => 'Black', 'code' => '#1F1F1F'],
            ['name' => 'White', 'code' => '#F7F3EA'],
            ['name' => 'Gray', 'code' => '#8C8C8C'],
            ['name' => 'Beige', 'code' => '#D9C6A3'],
            ['name' => 'Brown', 'code' => '#8B5A3C'],
            ['name' => 'Natural Oak', 'code' => '#C8A46B'],
            ['name' => 'Walnut', 'code' => '#6F4E37'],
            ['name' => 'Ivory', 'code' => '#F4EFE6'],
            ['name' => 'Charcoal', 'code' => '#323232'],
            ['name' => 'Sage', 'code' => '#8EA88B'],
            ['name' => 'Terracotta', 'code' => '#C76F51'],
            ['name' => 'Navy', 'code' => '#243B55'],
            ['name' => 'Sand', 'code' => '#D8C6A3'],
            ['name' => 'Olive', 'code' => '#6F7B4D'],
            ['name' => 'Rattan Natural', 'code' => '#B88955'],
            ['name' => 'Espresso', 'code' => '#3A2418'],
        ])->mapWithKeys(fn ($color) => [
            $color['name'] => Color::updateOrCreate(['name' => $color['name']], $color),
        ]);
    }

    private function seedBrands()
    {
        return collect([
            ['name' => 'Furemir', 'description' => 'Modern furniture for warm, practical homes.', 'index' => 1],
            ['name' => 'NordicNest', 'description' => 'Scandinavian inspired essentials with clean lines.', 'index' => 2],
            ['name' => 'CasaOak', 'description' => 'Solid wood furniture with timeless proportions.', 'index' => 3],
            ['name' => 'UrbanWeave', 'description' => 'Compact, textured furniture for city living.', 'index' => 4],
            ['name' => 'StudioForm', 'description' => 'Focused pieces for work, storage, and daily routines.', 'index' => 5],
            ['name' => 'PatioHaven', 'description' => 'Durable outdoor furniture for balconies and gardens.', 'index' => 6],
        ])->mapWithKeys(fn ($brand) => [
            $brand['name'] => Brand::updateOrCreate(['name' => $brand['name']], $brand),
        ]);
    }

    private function seedTags()
    {
        return collect([
            'Modern',
            'Scandinavian',
            'Compact',
            'Premium',
            'Natural',
            'Family',
            'Outdoor',
            'Storage',
            'Work-from-home',
            'Best Seller',
            'Office Chair',
            'Hanging Chair',
            'Folding Chair',
        ])->mapWithKeys(fn ($tag) => [
            $tag => Tag::updateOrCreate(['name' => $tag], ['name' => $tag]),
        ]);
    }

    private function seedFeaturedChairCategories($colors, $brands, $tags): void
    {
        $categorySeed = [
            'Office Chair' => [
                'description' => 'Ergonomic office chairs for focused home and studio work.',
                'index' => 7,
                'products' => [
                    ['name' => 'ErgoFlex Mesh Office Chair', 'brand' => 'StudioForm', 'price' => 6200000, 'material' => 'Breathable mesh, molded foam, nylon base', 'dimensions' => '66 x 66 x 108 cm', 'stock' => 24, 'sold' => 82, 'colors' => ['Black', 'Gray', 'Navy'], 'description' => 'A breathable mesh chair with adjustable lumbar support and a smooth tilt mechanism.'],
                    ['name' => 'LumbarPro Executive Chair', 'brand' => 'Furemir', 'price' => 8800000, 'material' => 'Vegan leather, steel frame, high-density foam', 'dimensions' => '70 x 72 x 116 cm', 'stock' => 18, 'sold' => 67, 'colors' => ['Black', 'Brown', 'Ivory'], 'description' => 'A high-back executive chair with padded armrests and generous lumbar support.'],
                    ['name' => 'TaskEase Compact Office Chair', 'brand' => 'NordicNest', 'price' => 4100000, 'material' => 'Fabric upholstery, powder-coated steel, foam cushion', 'dimensions' => '58 x 60 x 92 cm', 'stock' => 30, 'sold' => 49, 'colors' => ['Sage', 'Gray', 'Sand'], 'description' => 'A compact swivel chair for small desks, study corners, and apartment offices.'],
                    ['name' => 'AeroLift High Back Office Chair', 'brand' => 'StudioForm', 'price' => 7600000, 'material' => 'Mesh back, fabric seat, aluminum-look base', 'dimensions' => '68 x 68 x 120 cm', 'stock' => 20, 'sold' => 58, 'colors' => ['Charcoal', 'Black', 'White'], 'description' => 'A high-back office chair with a headrest, adjustable seat height, and stable five-star base.'],
                ],
            ],
            'Hanging Chair' => [
                'description' => 'Hanging and swing chairs for patios, reading nooks, and relaxed corners.',
                'index' => 8,
                'products' => [
                    ['name' => 'Rattan Cocoon Hanging Chair', 'brand' => 'UrbanWeave', 'price' => 9400000, 'material' => 'Handwoven rattan, steel stand, weather-resistant cushion', 'dimensions' => '105 x 105 x 198 cm', 'stock' => 14, 'sold' => 43, 'colors' => ['Rattan Natural', 'Brown', 'Black'], 'description' => 'A cocoon-shaped hanging chair with a freestanding frame and deep lounge cushion.'],
                    ['name' => 'Macrame Swing Hanging Chair', 'brand' => 'NordicNest', 'price' => 3900000, 'material' => 'Cotton macrame rope, beechwood rail', 'dimensions' => '82 x 60 x 125 cm', 'stock' => 22, 'sold' => 36, 'colors' => ['Ivory', 'Beige', 'Sage'], 'description' => 'A soft macrame swing chair for balconies, bedrooms, and calm reading corners.'],
                    ['name' => 'Patio Egg Hanging Chair', 'brand' => 'PatioHaven', 'price' => 11200000, 'material' => 'PE wicker, powder-coated steel, outdoor fabric cushion', 'dimensions' => '112 x 112 x 200 cm', 'stock' => 12, 'sold' => 39, 'colors' => ['Black', 'Rattan Natural', 'Gray'], 'description' => 'A patio egg chair with a sturdy stand and supportive outdoor cushion set.'],
                    ['name' => 'Cloud Pod Hanging Chair', 'brand' => 'Furemir', 'price' => 12800000, 'material' => 'Woven synthetic rattan, steel frame, plush cushion', 'dimensions' => '118 x 110 x 202 cm', 'stock' => 10, 'sold' => 31, 'colors' => ['Sand', 'Terracotta', 'Navy'], 'description' => 'A roomy pod-style hanging chair designed for sheltered patios and indoor lounge corners.'],
                ],
            ],
            'Folding Chair' => [
                'description' => 'Foldable chairs for flexible dining, events, balconies, and compact storage.',
                'index' => 9,
                'products' => [
                    ['name' => 'OakLine Folding Chair', 'brand' => 'CasaOak', 'price' => 2600000, 'material' => 'Solid oak frame, woven seat', 'dimensions' => '47 x 52 x 82 cm', 'stock' => 34, 'sold' => 52, 'colors' => ['Natural Oak', 'Walnut', 'Black'], 'description' => 'A warm wooden folding chair that stores flat and works well for dining or guests.'],
                    ['name' => 'Metro Metal Folding Chair', 'brand' => 'StudioForm', 'price' => 1800000, 'material' => 'Powder-coated steel, contoured metal seat', 'dimensions' => '45 x 50 x 80 cm', 'stock' => 42, 'sold' => 61, 'colors' => ['Black', 'White', 'Terracotta'], 'description' => 'A sturdy metal folding chair with a clean profile for events and everyday overflow seating.'],
                    ['name' => 'Canvas Camp Folding Chair', 'brand' => 'PatioHaven', 'price' => 2100000, 'material' => 'Beechwood frame, washable canvas sling', 'dimensions' => '55 x 58 x 78 cm', 'stock' => 38, 'sold' => 44, 'colors' => ['Olive', 'Sand', 'Navy'], 'description' => 'A relaxed canvas folding chair for patios, picnics, and casual outdoor seating.'],
                    ['name' => 'Bistro Slim Folding Chair', 'brand' => 'UrbanWeave', 'price' => 2300000, 'material' => 'Steel frame, woven synthetic seat and back', 'dimensions' => '46 x 50 x 83 cm', 'stock' => 36, 'sold' => 47, 'colors' => ['Sage', 'Charcoal', 'Beige'], 'description' => 'A slim bistro folding chair that tucks away easily after balcony meals or small gatherings.'],
                ],
            ],
        ];

        foreach ($categorySeed as $categoryName => $categoryData) {
            $category = Category::updateOrCreate(
                ['name' => $categoryName],
                [
                    'description' => $categoryData['description'],
                    'index' => $categoryData['index'],
                    'parent_id' => null,
                ]
            );

            foreach ($categoryData['products'] as $productData) {
                $product = Product::updateOrCreate(
                    ['name' => $productData['name']],
                    [
                        'description' => $productData['description'],
                        'short_description' => $productData['description'],
                        'material' => $productData['material'],
                        'rating' => 4.7,
                        'review_count' => $productData['sold'] + 18,
                        'category_id' => $category->category_id,
                        'brand_id' => $brands[$productData['brand']]->brand_id,
                        'amount_sold' => $productData['sold'],
                        'is_deleted' => false,
                    ]
                );

                ProductTag::updateOrCreate(
                    ['product_id' => $product->product_id, 'tag_id' => $tags[$categoryName]->tag_id],
                    ['product_id' => $product->product_id, 'tag_id' => $tags[$categoryName]->tag_id]
                );

                ProductTag::updateOrCreate(
                    ['product_id' => $product->product_id, 'tag_id' => $tags['Best Seller']->tag_id],
                    ['product_id' => $product->product_id, 'tag_id' => $tags['Best Seller']->tag_id]
                );

                $this->syncProductVariants($product->fresh('category'), $productData['colors'], $colors, [
                    'shape' => $this->shapeFor($productData['name'], $categoryName),
                    'material' => $productData['material'],
                    'dimensions' => $productData['dimensions'],
                    'price' => $productData['price'],
                    'stock' => $productData['stock'],
                    'description' => $productData['description'],
                ]);
            }
        }
    }

    private function completeProduct(Product $product, $colors, $tags): void
    {
        $meta = $this->metadataFor($product);

        $product->update([
            'short_description' => $meta['description'],
            'material' => $meta['material'],
            'rating' => $meta['rating'],
            'review_count' => $meta['reviews'],
        ]);

        foreach ($meta['tags'] as $tagName) {
            if (isset($tags[$tagName])) {
                ProductTag::updateOrCreate(
                    ['product_id' => $product->product_id, 'tag_id' => $tags[$tagName]->tag_id],
                    ['product_id' => $product->product_id, 'tag_id' => $tags[$tagName]->tag_id]
                );
            }
        }

        $this->syncProductVariants($product, $meta['colors'], $colors, $meta);
    }

    private function syncProductVariants(Product $product, array $colorNames, $colors, array $meta): void
    {
        $existingDetails = ProductDetail::where('product_id', $product->product_id)
            ->where('is_deleted', false)
            ->orderBy('sku')
            ->get()
            ->values();

        $baseSku = $existingDetails->first()?->sku ?: 'FURP-' . str_pad((string) $product->product_id, 4, '0', STR_PAD_LEFT);
        $baseSku = preg_replace('/-C\d+$/', '', $baseSku);

        foreach (array_values(array_unique($colorNames)) as $index => $colorName) {
            if (!isset($colors[$colorName])) {
                continue;
            }

            $sku = $existingDetails[$index]->sku ?? ($baseSku . '-C' . ($index + 1));
            $color = $colors[$colorName];
            $price = $meta['price'] ?? (int) ($existingDetails->first()?->original_price ?? 4500000);
            $stock = max(6, (int) (($meta['stock'] ?? $existingDetails->first()?->quantities ?? 18) - ($index * 2)));
            $dimensions = $meta['dimensions'] ?? ($existingDetails->first()?->size ?: '80 x 60 x 85 cm');
            $description = $meta['description'] ?? $product->short_description ?? $product->description;

            ProductDetail::updateOrCreate(
                ['sku' => $sku],
                [
                    'product_id' => $product->product_id,
                    'name' => $product->name . ' - ' . $colorName,
                    'description' => '<p>' . e($description) . '</p><p><strong>Material:</strong> ' . e($meta['material']) . '</p>',
                    'color_id' => $color->color_id,
                    'size' => $dimensions,
                    'original_price' => $price,
                    'warranty_month' => $meta['warranty'] ?? 24,
                    'quantities' => $stock,
                    'is_deleted' => false,
                ]
            );

            $fileName = Str::slug($product->name . '-' . $colorName) . '.jpg';
            $url = $this->makeProductImage($fileName, $meta['shape'], $color->code, $product->name, $colorName);

            ProductImage::where('sku', $sku)->delete();
            ProductImage::create([
                'sku' => $sku,
                'url' => $url,
                'alt_text' => $product->name . ' in ' . $colorName,
            ]);
        }
    }

    private function metadataFor(Product $product): array
    {
        $categoryName = $product->category?->name ?? '';
        $shape = $this->shapeFor($product->name, $categoryName);
        $details = ProductDetail::where('product_id', $product->product_id)->where('is_deleted', false)->first();

        return [
            'shape' => $shape,
            'material' => $this->materialFor($shape),
            'dimensions' => $details?->size ?: $this->dimensionsFor($shape),
            'price' => (int) ($details?->original_price ?? $this->priceFor($shape)),
            'stock' => (int) ($details?->quantities ?? 18),
            'warranty' => (int) ($details?->warranty_month ?? 24),
            'description' => $this->descriptionFor($product->name, $shape),
            'colors' => $this->colorsFor($shape, $details?->color?->name),
            'rating' => $this->ratingFor($product->product_id),
            'reviews' => 20 + (($product->product_id * 7) % 85),
            'tags' => $this->tagsFor($shape),
        ];
    }

    private function shapeFor(string $name, string $categoryName): string
    {
        $text = Str::lower($name . ' ' . $categoryName);

        return match (true) {
            str_contains($text, 'office chair'), str_contains($text, 'task chair'), str_contains($text, 'executive chair') => 'office-chair',
            str_contains($text, 'hanging chair'), str_contains($text, 'swing'), str_contains($text, 'cocoon'), str_contains($text, 'egg hanging'), str_contains($text, 'pod hanging') => 'hanging-chair',
            str_contains($text, 'folding chair') => 'folding-chair',
            str_contains($text, 'sofa'), str_contains($text, 'sectional') => 'sofa',
            str_contains($text, 'bed'), str_contains($text, 'mattress'), str_contains($text, 'headboard') => 'bed',
            str_contains($text, 'wardrobe') => 'wardrobe',
            str_contains($text, 'dresser'), str_contains($text, 'chest'), str_contains($text, 'drawer') => 'dresser',
            str_contains($text, 'desk'), str_contains($text, 'credenza'), str_contains($text, 'riser') => 'desk',
            str_contains($text, 'bookcase'), str_contains($text, 'bookshelf'), str_contains($text, 'shelf'), str_contains($text, 'organizer') => 'shelf',
            str_contains($text, 'cabinet'), str_contains($text, 'sideboard'), str_contains($text, 'console'), str_contains($text, 'stand') => 'cabinet',
            str_contains($text, 'dining set'), str_contains($text, 'dining table'), str_contains($text, 'meeting table'), str_contains($text, 'coffee table'), str_contains($text, 'side table'), str_contains($text, 'nesting table'), str_contains($text, 'bar cart') => 'table',
            str_contains($text, 'stool') => 'stool',
            str_contains($text, 'bench'), str_contains($text, 'ottoman') => 'bench',
            str_contains($text, 'chair') => 'chair',
            default => 'cabinet',
        };
    }

    private function materialFor(string $shape): string
    {
        return [
            'office-chair' => 'Breathable mesh, molded foam, steel and nylon frame',
            'hanging-chair' => 'Woven rattan or macrame rope with powder-coated steel support',
            'folding-chair' => 'Solid wood or powder-coated steel with reinforced folding hinges',
            'sofa' => 'Linen-blend upholstery, kiln-dried wood frame, high-density foam',
            'bed' => 'Upholstered frame, engineered wood support, foam padding',
            'wardrobe' => 'Engineered wood, laminate finish, metal rails',
            'dresser' => 'Oak veneer, engineered wood drawers, soft-close runners',
            'desk' => 'Oak veneer or powder-coated steel with cable-friendly storage',
            'shelf' => 'Engineered wood shelves with reinforced frame',
            'cabinet' => 'Walnut veneer, engineered wood body, metal hardware',
            'table' => 'Solid wood or marble-look engineered top with stable base',
            'stool' => 'Powder-coated steel frame with upholstered seat',
            'bench' => 'Solid frame with upholstered or woven seat',
            'chair' => 'Solid wood or metal frame with upholstered cushion',
        ][$shape] ?? 'Engineered wood, durable hardware, protective finish';
    }

    private function dimensionsFor(string $shape): string
    {
        return [
            'office-chair' => '66 x 66 x 108 cm',
            'hanging-chair' => '105 x 105 x 198 cm',
            'folding-chair' => '47 x 52 x 82 cm',
            'sofa' => '220 x 95 x 82 cm',
            'bed' => '170 x 215 x 110 cm',
            'wardrobe' => '200 x 62 x 220 cm',
            'dresser' => '150 x 48 x 82 cm',
            'desk' => '140 x 70 x 75 cm',
            'shelf' => '120 x 36 x 180 cm',
            'cabinet' => '160 x 42 x 72 cm',
            'table' => '140 x 80 x 75 cm',
            'stool' => '48 x 52 x 92 cm',
            'bench' => '120 x 42 x 46 cm',
            'chair' => '52 x 54 x 82 cm',
        ][$shape] ?? '90 x 45 x 80 cm';
    }

    private function priceFor(string $shape): int
    {
        return [
            'office-chair' => 6200000,
            'hanging-chair' => 9400000,
            'folding-chair' => 2400000,
            'sofa' => 18500000,
            'bed' => 14800000,
            'wardrobe' => 22800000,
            'dresser' => 12600000,
            'desk' => 7600000,
            'shelf' => 7600000,
            'cabinet' => 9800000,
            'table' => 7200000,
            'stool' => 2800000,
            'bench' => 4500000,
            'chair' => 5200000,
        ][$shape] ?? 4500000;
    }

    private function descriptionFor(string $name, string $shape): string
    {
        return [
            'office-chair' => "$name is built for daily work sessions with supportive seating, smooth adjustment, and a stable base.",
            'hanging-chair' => "$name creates a relaxed hanging seat with a secure frame and a lounge-ready cushion.",
            'folding-chair' => "$name folds flat for storage while keeping a sturdy, comfortable seat for flexible spaces.",
            'sofa' => "$name offers generous comfort, balanced proportions, and upholstery suited to everyday lounging.",
            'bed' => "$name brings calm bedroom comfort with durable support and a soft, easy-to-style profile.",
            'wardrobe' => "$name keeps clothing organized with practical rails, shelves, and a clean exterior.",
            'dresser' => "$name adds reliable drawer storage with a warm furniture finish and smooth daily operation.",
            'desk' => "$name supports focused work with a stable surface and smart storage-friendly proportions.",
            'shelf' => "$name provides vertical storage for books, baskets, decor, and everyday organization.",
            'cabinet' => "$name keeps media, dinnerware, or essentials tucked away behind a refined storage front.",
            'table' => "$name creates a practical surface for meals, hosting, display, or daily living routines.",
            'stool' => "$name gives compact counter-height seating with a supportive frame and clean silhouette.",
            'bench' => "$name adds flexible seating and storage-friendly utility for entryways, dining, or bedrooms.",
            'chair' => "$name adds a comfortable accent seat with a balanced shape and durable construction.",
        ][$shape] ?? "$name is a practical furniture piece designed for everyday home use.";
    }

    private function colorsFor(string $shape, ?string $currentColor): array
    {
        $fallbacks = [
            'office-chair' => ['Black', 'Gray', 'Navy'],
            'hanging-chair' => ['Rattan Natural', 'Brown', 'Ivory'],
            'folding-chair' => ['Natural Oak', 'Black', 'Olive'],
            'sofa' => ['Ivory', 'Sage', 'Navy'],
            'bed' => ['Sand', 'Ivory', 'Charcoal'],
            'wardrobe' => ['Ivory', 'Walnut', 'Charcoal'],
            'dresser' => ['Natural Oak', 'Walnut', 'White'],
            'desk' => ['Natural Oak', 'Charcoal', 'Ivory'],
            'shelf' => ['Walnut', 'Natural Oak', 'Black'],
            'cabinet' => ['Walnut', 'Charcoal', 'Ivory'],
            'table' => ['Natural Oak', 'Walnut', 'Black'],
            'stool' => ['Charcoal', 'Terracotta', 'Ivory'],
            'bench' => ['Sand', 'Sage', 'Walnut'],
            'chair' => ['Natural Oak', 'Sage', 'Charcoal'],
        ][$shape] ?? ['Natural Oak', 'Walnut', 'Black'];

        if ($currentColor && !in_array($currentColor, $fallbacks, true)) {
            array_unshift($fallbacks, $currentColor);
        }

        return array_slice(array_values(array_unique($fallbacks)), 0, 3);
    }

    private function tagsFor(string $shape): array
    {
        return [
            'office-chair' => ['Office Chair', 'Work-from-home', 'Modern'],
            'hanging-chair' => ['Hanging Chair', 'Outdoor', 'Natural'],
            'folding-chair' => ['Folding Chair', 'Compact', 'Outdoor'],
            'shelf' => ['Storage', 'Compact'],
            'cabinet' => ['Storage', 'Modern'],
            'desk' => ['Work-from-home', 'Modern'],
            'sofa' => ['Family', 'Modern'],
        ][$shape] ?? ['Modern'];
    }

    private function ratingFor(int $productId): float
    {
        return [4.5, 4.6, 4.7, 4.8, 4.9][$productId % 5];
    }

    private function makeProductImage(string $fileName, string $shape, string $hex, string $productName, string $colorName): string
    {
        $directory = public_path('images/products');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $fileName;

        if (!extension_loaded('gd')) {
            return config('app.url') . 'images/products/' . $fileName;
        }

        $image = imagecreatetruecolor(900, 700);
        imageantialias($image, true);

        $bg = imagecolorallocate($image, 246, 243, 238);
        $floor = imagecolorallocate($image, 229, 222, 211);
        $line = imagecolorallocate($image, 42, 42, 42);
        $shadow = imagecolorallocatealpha($image, 60, 50, 45, 92);
        $product = $this->allocateHex($image, $hex);
        $light = $this->blendColor($image, $hex, 42);
        $dark = $this->blendColor($image, $hex, -45);

        imagefilledrectangle($image, 0, 0, 900, 700, $bg);
        imagefilledellipse($image, 450, 575, 520, 70, $shadow);
        imagefilledrectangle($image, 0, 575, 900, 700, $floor);

        $this->drawShape($image, $shape, $product, $light, $dark, $line);

        $caption = imagecolorallocate($image, 70, 64, 58);
        imagestring($image, 5, 34, 34, $this->shortLabel($productName), $caption);
        imagestring($image, 3, 36, 58, $colorName, $caption);

        imagejpeg($image, $path, 88);
        imagedestroy($image);

        return config('app.url') . 'images/products/' . $fileName;
    }

    private function drawShape($image, string $shape, int $product, int $light, int $dark, int $line): void
    {
        match ($shape) {
            'office-chair' => $this->drawOfficeChair($image, $product, $light, $dark, $line),
            'hanging-chair' => $this->drawHangingChair($image, $product, $light, $dark, $line),
            'folding-chair' => $this->drawFoldingChair($image, $product, $light, $dark, $line),
            'sofa' => $this->drawSofa($image, $product, $light, $dark, $line),
            'bed' => $this->drawBed($image, $product, $light, $dark, $line),
            'wardrobe' => $this->drawWardrobe($image, $product, $light, $dark, $line),
            'dresser' => $this->drawDresser($image, $product, $light, $dark, $line),
            'desk' => $this->drawDesk($image, $product, $light, $dark, $line),
            'shelf' => $this->drawShelf($image, $product, $light, $dark, $line),
            'table' => $this->drawTable($image, $product, $light, $dark, $line),
            'stool' => $this->drawStool($image, $product, $light, $dark, $line),
            'bench' => $this->drawBench($image, $product, $light, $dark, $line),
            default => $this->drawCabinet($image, $product, $light, $dark, $line),
        };
    }

    private function drawOfficeChair($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 330, 170, 570, 380, 28, $c);
        $this->roundedRect($im, 350, 200, 550, 360, 22, $light);
        $this->roundedRect($im, 315, 370, 585, 455, 26, $dark);
        imagesetthickness($im, 10);
        imageline($im, 450, 455, 450, 545, $line);
        imageline($im, 450, 545, 350, 585, $line);
        imageline($im, 450, 545, 550, 585, $line);
        imageline($im, 450, 545, 450, 590, $line);
        imagefilledellipse($im, 350, 590, 34, 20, $line);
        imagefilledellipse($im, 550, 590, 34, 20, $line);
        imagefilledellipse($im, 450, 596, 34, 20, $line);
    }

    private function drawHangingChair($im, $c, $light, $dark, $line): void
    {
        imagesetthickness($im, 12);
        imageline($im, 620, 150, 620, 570, $line);
        imageline($im, 620, 150, 430, 150, $line);
        imageline($im, 620, 570, 710, 610, $line);
        imageline($im, 620, 570, 530, 610, $line);
        imageline($im, 430, 150, 430, 225, $line);
        imagefilledellipse($im, 430, 380, 250, 330, $dark);
        imagefilledellipse($im, 430, 380, 190, 250, $c);
        imagefilledellipse($im, 430, 405, 132, 158, $light);
    }

    private function drawFoldingChair($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 350, 180, 550, 330, 18, $c);
        $this->roundedRect($im, 330, 350, 570, 420, 18, $light);
        imagesetthickness($im, 9);
        imageline($im, 360, 420, 300, 585, $line);
        imageline($im, 540, 420, 600, 585, $line);
        imageline($im, 330, 585, 570, 420, $line);
        imageline($im, 570, 585, 330, 420, $line);
    }

    private function drawSofa($im, $c, $light, $dark): void
    {
        $this->roundedRect($im, 230, 260, 670, 430, 32, $c);
        $this->roundedRect($im, 205, 345, 695, 490, 28, $dark);
        $this->roundedRect($im, 245, 285, 430, 405, 18, $light);
        $this->roundedRect($im, 470, 285, 655, 405, 18, $light);
        imagefilledrectangle($im, 270, 490, 300, 565, $dark);
        imagefilledrectangle($im, 600, 490, 630, 565, $dark);
    }

    private function drawBed($im, $c, $light, $dark): void
    {
        $this->roundedRect($im, 250, 230, 650, 420, 18, $dark);
        $this->roundedRect($im, 220, 360, 700, 505, 20, $c);
        $this->roundedRect($im, 265, 285, 410, 350, 16, $light);
        $this->roundedRect($im, 430, 285, 575, 350, 16, $light);
        imagefilledrectangle($im, 260, 505, 295, 565, $dark);
        imagefilledrectangle($im, 625, 505, 660, 565, $dark);
    }

    private function drawWardrobe($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 285, 130, 615, 570, 18, $c);
        imageline($im, 450, 145, 450, 555, $line);
        imagefilledellipse($im, 430, 350, 12, 12, $dark);
        imagefilledellipse($im, 470, 350, 12, 12, $dark);
        $this->roundedRect($im, 320, 170, 410, 250, 10, $light);
        $this->roundedRect($im, 490, 170, 580, 250, 10, $light);
    }

    private function drawDresser($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 270, 245, 630, 530, 18, $c);
        for ($y = 280; $y <= 450; $y += 85) {
            $this->roundedRect($im, 305, $y, 595, $y + 54, 10, $light);
            imageline($im, 435, $y + 27, 465, $y + 27, $line);
        }
        imagefilledrectangle($im, 310, 530, 340, 575, $dark);
        imagefilledrectangle($im, 560, 530, 590, 575, $dark);
    }

    private function drawDesk($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 240, 300, 660, 360, 14, $c);
        imagefilledrectangle($im, 280, 360, 315, 565, $dark);
        imagefilledrectangle($im, 585, 360, 620, 565, $dark);
        $this->roundedRect($im, 350, 380, 540, 500, 12, $light);
        imageline($im, 370, 425, 520, 425, $line);
        imageline($im, 370, 465, 520, 465, $line);
    }

    private function drawShelf($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 290, 150, 610, 560, 16, $c);
        for ($y = 245; $y <= 455; $y += 105) {
            imageline($im, 310, $y, 590, $y, $line);
        }
        imageline($im, 450, 165, 450, 545, $line);
        imagefilledrectangle($im, 330, 185, 420, 225, $light);
        imagefilledrectangle($im, 475, 290, 560, 340, $light);
        imagefilledrectangle($im, 325, 395, 410, 445, $dark);
    }

    private function drawCabinet($im, $c, $light, $dark, $line): void
    {
        $this->roundedRect($im, 250, 260, 650, 520, 18, $c);
        imageline($im, 450, 280, 450, 500, $line);
        $this->roundedRect($im, 300, 305, 405, 450, 10, $light);
        $this->roundedRect($im, 495, 305, 600, 450, 10, $light);
        imagefilledrectangle($im, 300, 520, 330, 565, $dark);
        imagefilledrectangle($im, 570, 520, 600, 565, $dark);
    }

    private function drawTable($im, $c, $light, $dark): void
    {
        $this->roundedRect($im, 240, 285, 660, 360, 16, $c);
        imagefilledrectangle($im, 290, 360, 325, 570, $dark);
        imagefilledrectangle($im, 575, 360, 610, 570, $dark);
        imagefilledellipse($im, 450, 300, 280, 50, $light);
    }

    private function drawStool($im, $c, $light, $dark, $line): void
    {
        imagefilledellipse($im, 450, 300, 240, 85, $c);
        imagesetthickness($im, 8);
        imageline($im, 390, 335, 340, 570, $line);
        imageline($im, 510, 335, 560, 570, $line);
        imageline($im, 375, 440, 525, 440, $dark);
        imagefilledellipse($im, 450, 300, 170, 45, $light);
    }

    private function drawBench($im, $c, $light, $dark): void
    {
        $this->roundedRect($im, 270, 320, 630, 410, 24, $c);
        $this->roundedRect($im, 300, 275, 600, 325, 20, $light);
        imagefilledrectangle($im, 310, 410, 345, 565, $dark);
        imagefilledrectangle($im, 555, 410, 590, 565, $dark);
    }

    private function roundedRect($im, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function allocateHex($im, string $hex): int
    {
        $rgb = $this->hexToRgb($hex);
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function blendColor($im, string $hex, int $amount): int
    {
        $rgb = array_map(fn ($value) => max(0, min(255, $value + $amount)), $this->hexToRgb($hex));
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function shortLabel(string $text): string
    {
        return mb_strimwidth($text, 0, 46, '...');
    }
}
