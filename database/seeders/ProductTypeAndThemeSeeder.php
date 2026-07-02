<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductType;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductTypeAndThemeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $types = collect($this->types())->mapWithKeys(fn ($type, $index) => [
                $type['name'] => ProductType::updateOrCreate(
                    ['name' => $type['name']],
                    [
                        'description' => $type['description'],
                        'index' => $index + 1,
                    ]
                ),
            ]);

            $tags = collect($this->themeTags())->mapWithKeys(fn ($name) => [
                $name => Tag::updateOrCreate(['name' => $name], ['name' => $name]),
            ]);

            Product::with(['category', 'brand', 'detailed_products.color'])->where('is_deleted', false)->get()
                ->each(function (Product $product) use ($types, $tags) {
                    $typeName = $this->detectType($product);
                    $product->forceFill([
                        'product_type_id' => $types[$typeName]->product_type_id ?? null,
                    ])->save();

                    foreach ($this->detectThemes($product, $typeName) as $themeName) {
                        ProductTag::updateOrCreate(
                            ['product_id' => $product->product_id, 'tag_id' => $tags[$themeName]->tag_id],
                            ['product_id' => $product->product_id, 'tag_id' => $tags[$themeName]->tag_id]
                        );
                    }
                });
        });
    }

    private function types(): array
    {
        return [
            ['name' => 'Sofa', 'description' => 'Sofas and modular seating for living rooms.'],
            ['name' => 'Chair', 'description' => 'Dining chairs, lounge chairs, office chairs, hanging chairs and folding chairs.'],
            ['name' => 'Table', 'description' => 'Coffee tables, dining tables, side tables, console tables and outdoor tables.'],
            ['name' => 'Bed', 'description' => 'Beds, mattresses and headboards for bedrooms.'],
            ['name' => 'Wardrobe', 'description' => 'Wardrobes and tall bedroom storage pieces.'],
            ['name' => 'Cabinet & Storage', 'description' => 'Cabinets, media consoles, dressers, chests and storage units.'],
            ['name' => 'Desk', 'description' => 'Office desks, vanity desks and compact work surfaces.'],
            ['name' => 'Bench', 'description' => 'Benches for dining rooms, bedrooms and entryways.'],
            ['name' => 'Outdoor Furniture', 'description' => 'Weather-ready furniture for balconies, patios and gardens.'],
            ['name' => 'Decor & Accessories', 'description' => 'Accent furniture and supporting home accessories.'],
        ];
    }

    private function themeTags(): array
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
        ];
    }

    private function detectType(Product $product): string
    {
        $text = $this->typeSearchableText($product);

        $rules = [
            'Desk' => ['desk', 'workstation', 'vanity'],
            'Bench' => ['bench'],
            'Wardrobe' => ['wardrobe'],
            'Chair' => ['chair', 'armchair', 'recliner', 'stool', 'hanging', 'folding'],
            'Cabinet & Storage' => ['cabinet', 'dresser', 'chest', 'console', 'sideboard', 'stand', 'storage', 'shelf', 'bookcase', 'credenza', 'drawer', 'organizer', 'unit'],
            'Bed' => [' bed', 'bed ', 'mattress', 'headboard'],
            'Table' => ['table', 'coffee'],
            'Outdoor Furniture' => ['outdoor', 'patio', 'balcony', 'garden'],
            'Sofa' => ['sofa', 'loveseat', 'sectional'],
        ];

        foreach ($rules as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($text, $keyword)) {
                    return $type;
                }
            }
        }

        return 'Decor & Accessories';
    }

    private function detectThemes(Product $product, string $typeName): array
    {
        $text = $this->searchableText($product);
        $themes = [];

        foreach (['Living Room', 'Bedroom', 'Dining', 'Office'] as $theme) {
            if (Str::contains($text, Str::lower($theme))) {
                $themes[] = $theme;
            }
        }

        if (Str::contains($text, ['compact', 'small', 'slim', 'folding'])) {
            $themes[] = 'Small Space';
            $themes[] = 'Space Saving';
        }

        if (Str::contains($text, ['storage', 'drawer', 'wardrobe', 'cabinet', 'chest', 'shelf'])) {
            $themes[] = 'Easy Storage';
        }

        if (Str::contains($text, ['oak', 'walnut', 'wood', 'rattan', 'natural'])) {
            $themes[] = 'Natural Wood';
        }

        if (Str::contains($text, ['upholstered', 'linen', 'boucle', 'fabric', 'cushion'])) {
            $themes[] = 'Upholstered';
        }

        if (Str::contains($text, ['lounge', 'relax', 'recliner', 'hanging'])) {
            $themes[] = 'Relax Corner';
        }

        if (Str::contains($text, ['outdoor', 'patio', 'balcony', 'garden']) || $typeName === 'Outdoor Furniture') {
            $themes[] = 'Outdoor Living';
        }

        if (Str::contains($text, ['premium', 'marble', 'statement', 'sculptural'])) {
            $themes[] = 'Statement Piece';
        }

        return collect($themes)->unique()->values()->all();
    }

    private function searchableText(Product $product): string
    {
        return Str::lower(implode(' ', [
            $product->name,
            $product->description,
            $product->short_description,
            $product->material,
            $product->category?->name,
            $product->brand?->name,
            $product->detailed_products->pluck('name')->implode(' '),
            $product->detailed_products->pluck('description')->implode(' '),
            $product->detailed_products->pluck('color.name')->implode(' '),
        ]));
    }

    private function typeSearchableText(Product $product): string
    {
        return Str::lower(implode(' ', [
            ' ' . $product->name . ' ',
            $product->category?->name,
            $product->detailed_products->pluck('name')->implode(' '),
        ]));
    }
}
