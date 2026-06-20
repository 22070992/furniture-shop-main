<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductDiscounts;
use App\Models\ProductImage;
use App\Models\ProductTag;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $colors = collect([
                ['name' => 'Natural Oak', 'code' => '#C8A46B'],
                ['name' => 'Walnut', 'code' => '#6F4E37'],
                ['name' => 'Ivory', 'code' => '#F4EFE6'],
                ['name' => 'Charcoal', 'code' => '#323232'],
                ['name' => 'Sage', 'code' => '#8EA88B'],
                ['name' => 'Terracotta', 'code' => '#C76F51'],
                ['name' => 'Navy', 'code' => '#243B55'],
                ['name' => 'Sand', 'code' => '#D8C6A3'],
            ])->mapWithKeys(fn ($color) => [
                $color['name'] => Color::updateOrCreate(['name' => $color['name']], $color),
            ]);

            $brands = collect([
                ['name' => 'Furemir', 'description' => 'Modern furniture for warm, practical homes.', 'index' => 1],
                ['name' => 'NordicNest', 'description' => 'Scandinavian inspired essentials with clean lines.', 'index' => 2],
                ['name' => 'CasaOak', 'description' => 'Solid wood furniture with timeless proportions.', 'index' => 3],
                ['name' => 'UrbanWeave', 'description' => 'Compact, textured furniture for city living.', 'index' => 4],
                ['name' => 'StudioForm', 'description' => 'Focused pieces for work, storage, and daily routines.', 'index' => 5],
                ['name' => 'PatioHaven', 'description' => 'Durable outdoor furniture for balconies and gardens.', 'index' => 6],
            ])->mapWithKeys(fn ($brand) => [
                $brand['name'] => Brand::updateOrCreate(['name' => $brand['name']], $brand),
            ]);

            $tags = collect([
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
            ])->mapWithKeys(fn ($tag) => [
                $tag => Tag::updateOrCreate(['name' => $tag], ['name' => $tag]),
            ]);

            $discount = Discount::updateOrCreate(
                ['title' => 'Furemir Launch Deal'],
                [
                    'description' => 'Introductory discount for selected Furemir catalog items.',
                    'percentage' => 12,
                    'start_date' => now()->subDay()->toDateString(),
                    'end_date' => now()->addMonth()->toDateString(),
                    'is_active' => true,
                ]
            );

            $catalog = $this->catalog();

            foreach ($catalog as $categoryIndex => $categoryData) {
                $category = Category::updateOrCreate(
                    ['name' => $categoryData['name']],
                    [
                        'description' => $categoryData['description'],
                        'index' => $categoryIndex + 1,
                        'parent_id' => null,
                    ]
                );

                foreach ($categoryData['products'] as $productIndex => $item) {
                    $brand = $brands[$item['brand']];
                    $product = Product::updateOrCreate(
                        ['name' => $item['name']],
                        [
                            'description' => $item['description'],
                            'category_id' => $category->category_id,
                            'brand_id' => $brand->brand_id,
                            'amount_sold' => $item['sold'],
                            'is_deleted' => false,
                        ]
                    );

                    $color = $colors[$item['color']];
                    $sku = 'FUR-' . str_pad((string) ($categoryIndex + 1), 2, '0', STR_PAD_LEFT)
                        . '-' . str_pad((string) ($productIndex + 1), 3, '0', STR_PAD_LEFT);

                    ProductDetail::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'product_id' => $product->product_id,
                            'name' => $item['name'] . ' - ' . $item['color'],
                            'description' => '<p>' . $item['description'] . '</p><p>Designed for reliable daily use with balanced proportions, durable materials, and an easy-to-style finish.</p>',
                            'color_id' => $color->color_id,
                            'size' => $item['size'],
                            'original_price' => $item['price'],
                            'warranty_month' => $item['warranty'],
                            'quantities' => $item['stock'],
                            'is_deleted' => false,
                        ]
                    );

                    ProductImage::updateOrCreate(
                        ['sku' => $sku, 'url' => $categoryData['image']],
                        ['sku' => $sku, 'url' => $categoryData['image']]
                    );

                    foreach ($item['tags'] as $tagName) {
                        ProductTag::updateOrCreate(
                            ['product_id' => $product->product_id, 'tag_id' => $tags[$tagName]->tag_id],
                            ['product_id' => $product->product_id, 'tag_id' => $tags[$tagName]->tag_id]
                        );
                    }

                    if ($productIndex < 2) {
                        ProductDiscounts::updateOrCreate(
                            ['sku' => $sku, 'discount_id' => $discount->discount_id],
                            ['sku' => $sku, 'discount_id' => $discount->discount_id]
                        );
                    }
                }
            }
        });
    }

    private function catalog(): array
    {
        return [
            [
                'name' => 'Living Room',
                'description' => 'Sofas, tables, lounge chairs, and media storage for welcoming living spaces.',
                'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=80',
                'products' => [
                    ['name' => 'Aalto Modular Linen Sofa', 'brand' => 'Furemir', 'color' => 'Ivory', 'size' => '220 x 95 x 82 cm', 'price' => 18500000, 'warranty' => 36, 'stock' => 18, 'sold' => 72, 'tags' => ['Modern', 'Family', 'Best Seller'], 'description' => 'A deep modular sofa with soft linen upholstery and removable cushions for relaxed family seating.'],
                    ['name' => 'Mira Low Oak Coffee Table', 'brand' => 'CasaOak', 'color' => 'Natural Oak', 'size' => '120 x 60 x 38 cm', 'price' => 5200000, 'warranty' => 24, 'stock' => 28, 'sold' => 64, 'tags' => ['Natural', 'Scandinavian'], 'description' => 'A low-profile oak coffee table with a broad top and rounded corners for everyday entertaining.'],
                    ['name' => 'Lumi Curved Accent Chair', 'brand' => 'NordicNest', 'color' => 'Sage', 'size' => '78 x 76 x 80 cm', 'price' => 6900000, 'warranty' => 24, 'stock' => 16, 'sold' => 43, 'tags' => ['Modern', 'Compact'], 'description' => 'A sculptural accent chair with a curved back that softens reading corners and small living rooms.'],
                    ['name' => 'Sora Marble Nesting Tables', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '70 x 45 x 45 cm', 'price' => 4800000, 'warranty' => 18, 'stock' => 24, 'sold' => 38, 'tags' => ['Premium', 'Compact'], 'description' => 'Two nesting tables with marble-look tops that slide together when floor space matters.'],
                    ['name' => 'Vela Walnut Media Console', 'brand' => 'CasaOak', 'color' => 'Walnut', 'size' => '180 x 42 x 52 cm', 'price' => 11200000, 'warranty' => 36, 'stock' => 12, 'sold' => 51, 'tags' => ['Storage', 'Natural'], 'description' => 'A walnut media console with cable cutouts, sliding doors, and hidden shelf storage.'],
                    ['name' => 'Nori Boucle Lounge Chair', 'brand' => 'Furemir', 'color' => 'Sand', 'size' => '82 x 84 x 76 cm', 'price' => 7800000, 'warranty' => 24, 'stock' => 15, 'sold' => 35, 'tags' => ['Premium', 'Modern'], 'description' => 'A plush lounge chair wrapped in textured boucle for a quiet, elevated living room note.'],
                    ['name' => 'Arden Recliner Armchair', 'brand' => 'UrbanWeave', 'color' => 'Navy', 'size' => '92 x 96 x 103 cm', 'price' => 9800000, 'warranty' => 30, 'stock' => 10, 'sold' => 41, 'tags' => ['Family', 'Best Seller'], 'description' => 'A compact recliner with a supportive back, smooth mechanism, and apartment-friendly footprint.'],
                    ['name' => 'Tova Slim Console Table', 'brand' => 'NordicNest', 'color' => 'Natural Oak', 'size' => '110 x 32 x 78 cm', 'price' => 3900000, 'warranty' => 18, 'stock' => 30, 'sold' => 29, 'tags' => ['Compact', 'Scandinavian'], 'description' => 'A slim console table for entryways, sofa backs, or narrow walls that need a useful landing spot.'],
                    ['name' => 'Rhea Round Side Table', 'brand' => 'Furemir', 'color' => 'Terracotta', 'size' => '48 x 48 x 52 cm', 'price' => 2600000, 'warranty' => 12, 'stock' => 34, 'sold' => 25, 'tags' => ['Modern', 'Compact'], 'description' => 'A rounded side table with a warm accent finish, ideal beside sofas and lounge chairs.'],
                    ['name' => 'Kara Floating TV Stand', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '160 x 36 x 32 cm', 'price' => 7300000, 'warranty' => 24, 'stock' => 14, 'sold' => 47, 'tags' => ['Storage', 'Modern'], 'description' => 'A wall-mounted TV stand that keeps media gear tidy while freeing up the floor below.'],
                ],
            ],
            [
                'name' => 'Bedroom',
                'description' => 'Beds, wardrobes, dressers, nightstands, and bedroom accents for calm rest.',
                'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80',
                'products' => [
                    ['name' => 'Elan Upholstered Queen Bed', 'brand' => 'Furemir', 'color' => 'Sand', 'size' => '170 x 215 x 110 cm', 'price' => 14800000, 'warranty' => 36, 'stock' => 13, 'sold' => 68, 'tags' => ['Modern', 'Premium', 'Best Seller'], 'description' => 'A queen bed with a softly padded headboard and low side rails for a calm bedroom center.'],
                    ['name' => 'Nara Walnut Nightstand', 'brand' => 'CasaOak', 'color' => 'Walnut', 'size' => '50 x 42 x 55 cm', 'price' => 3600000, 'warranty' => 24, 'stock' => 26, 'sold' => 57, 'tags' => ['Natural', 'Storage'], 'description' => 'A compact walnut nightstand with two drawers and a quiet recessed pull detail.'],
                    ['name' => 'Olin Six Drawer Dresser', 'brand' => 'CasaOak', 'color' => 'Natural Oak', 'size' => '150 x 48 x 82 cm', 'price' => 12600000, 'warranty' => 36, 'stock' => 11, 'sold' => 34, 'tags' => ['Storage', 'Natural'], 'description' => 'A wide dresser with smooth drawers for clothing, linens, and everyday bedroom storage.'],
                    ['name' => 'Vega Sliding Wardrobe', 'brand' => 'StudioForm', 'color' => 'Ivory', 'size' => '200 x 62 x 220 cm', 'price' => 22800000, 'warranty' => 48, 'stock' => 8, 'sold' => 31, 'tags' => ['Storage', 'Premium'], 'description' => 'A sliding wardrobe with full-height doors, hanging rails, and modular shelf storage inside.'],
                    ['name' => 'Mia Vanity Desk', 'brand' => 'NordicNest', 'color' => 'Ivory', 'size' => '100 x 45 x 78 cm', 'price' => 6100000, 'warranty' => 24, 'stock' => 17, 'sold' => 28, 'tags' => ['Scandinavian', 'Compact'], 'description' => 'A bright vanity desk with a clean worktop and drawers for beauty essentials or writing tools.'],
                    ['name' => 'Cloud Rest Mattress', 'brand' => 'Furemir', 'color' => 'Ivory', 'size' => '160 x 200 x 28 cm', 'price' => 9700000, 'warranty' => 60, 'stock' => 20, 'sold' => 49, 'tags' => ['Premium', 'Family'], 'description' => 'A medium-firm mattress with layered comfort foam and stable edge support for nightly use.'],
                    ['name' => 'Tessa Storage Bench', 'brand' => 'UrbanWeave', 'color' => 'Sage', 'size' => '120 x 42 x 46 cm', 'price' => 4500000, 'warranty' => 18, 'stock' => 22, 'sold' => 33, 'tags' => ['Storage', 'Compact'], 'description' => 'An upholstered bench with hidden storage for extra throws, pillows, and seasonal bedding.'],
                    ['name' => 'Lova Rattan Headboard', 'brand' => 'UrbanWeave', 'color' => 'Natural Oak', 'size' => '165 x 6 x 115 cm', 'price' => 3900000, 'warranty' => 18, 'stock' => 18, 'sold' => 26, 'tags' => ['Natural', 'Scandinavian'], 'description' => 'A woven rattan headboard that adds natural texture without weighing down the room.'],
                    ['name' => 'Ari Five Drawer Chest', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '82 x 48 x 118 cm', 'price' => 8800000, 'warranty' => 30, 'stock' => 12, 'sold' => 36, 'tags' => ['Storage', 'Modern'], 'description' => 'A tall chest that uses vertical space efficiently while keeping clothing sorted and accessible.'],
                    ['name' => 'Solana Bedside Cabinet', 'brand' => 'NordicNest', 'color' => 'Sand', 'size' => '45 x 40 x 62 cm', 'price' => 3200000, 'warranty' => 18, 'stock' => 24, 'sold' => 22, 'tags' => ['Compact', 'Scandinavian'], 'description' => 'A small bedside cabinet with one drawer and one open shelf for easy nighttime reach.'],
                ],
            ],
            [
                'name' => 'Dining Room',
                'description' => 'Dining tables, chairs, benches, stools, sideboards, and display cabinets.',
                'image' => 'https://images.unsplash.com/photo-1449247709967-d4461a6a6103?auto=format&fit=crop&w=900&q=80',
                'products' => [
                    ['name' => 'Hana Oak Dining Table', 'brand' => 'CasaOak', 'color' => 'Natural Oak', 'size' => '180 x 90 x 75 cm', 'price' => 13200000, 'warranty' => 36, 'stock' => 12, 'sold' => 59, 'tags' => ['Natural', 'Family', 'Best Seller'], 'description' => 'A solid oak dining table sized for weekday meals, weekend hosting, and shared projects.'],
                    ['name' => 'Iris Four Chair Dining Set', 'brand' => 'Furemir', 'color' => 'Walnut', 'size' => '140 x 80 x 75 cm', 'price' => 16800000, 'warranty' => 36, 'stock' => 9, 'sold' => 46, 'tags' => ['Family', 'Modern'], 'description' => 'A coordinated dining set with a warm walnut table and four supportive upholstered chairs.'],
                    ['name' => 'Cleo Round Marble Table', 'brand' => 'StudioForm', 'color' => 'Ivory', 'size' => '110 x 110 x 75 cm', 'price' => 11800000, 'warranty' => 30, 'stock' => 10, 'sold' => 35, 'tags' => ['Premium', 'Compact'], 'description' => 'A round dining table with a marble-look top that keeps conversation close in smaller rooms.'],
                    ['name' => 'Rowan Counter Stool', 'brand' => 'UrbanWeave', 'color' => 'Charcoal', 'size' => '48 x 52 x 92 cm', 'price' => 2800000, 'warranty' => 18, 'stock' => 36, 'sold' => 44, 'tags' => ['Compact', 'Modern'], 'description' => 'A counter-height stool with a curved back and foot rail for breakfast bars and kitchen islands.'],
                    ['name' => 'Mae Sideboard Buffet', 'brand' => 'CasaOak', 'color' => 'Walnut', 'size' => '160 x 45 x 82 cm', 'price' => 13900000, 'warranty' => 36, 'stock' => 8, 'sold' => 33, 'tags' => ['Storage', 'Natural'], 'description' => 'A sideboard buffet with drawers and doors for dinnerware, linens, and serving pieces.'],
                    ['name' => 'Niko Rolling Bar Cart', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '76 x 40 x 86 cm', 'price' => 4200000, 'warranty' => 18, 'stock' => 18, 'sold' => 27, 'tags' => ['Compact', 'Modern'], 'description' => 'A rolling bar cart with two trays and guard rails for drinks, glassware, or coffee service.'],
                    ['name' => 'Orla Upholstered Dining Bench', 'brand' => 'Furemir', 'color' => 'Sand', 'size' => '150 x 42 x 48 cm', 'price' => 5600000, 'warranty' => 24, 'stock' => 15, 'sold' => 30, 'tags' => ['Family', 'Scandinavian'], 'description' => 'An upholstered dining bench that tucks neatly beneath rectangular tables after meals.'],
                    ['name' => 'Pavo Glass Display Cabinet', 'brand' => 'NordicNest', 'color' => 'Ivory', 'size' => '90 x 40 x 180 cm', 'price' => 10400000, 'warranty' => 30, 'stock' => 10, 'sold' => 24, 'tags' => ['Storage', 'Scandinavian'], 'description' => 'A glass-front cabinet for displaying ceramics, books, and hosting essentials.'],
                    ['name' => 'Riko Extendable Table', 'brand' => 'CasaOak', 'color' => 'Natural Oak', 'size' => '160-220 x 90 x 75 cm', 'price' => 17600000, 'warranty' => 48, 'stock' => 7, 'sold' => 40, 'tags' => ['Family', 'Premium'], 'description' => 'An extendable dining table that expands for guests and returns to everyday scale.'],
                    ['name' => 'Sena Cane Dining Chair', 'brand' => 'UrbanWeave', 'color' => 'Natural Oak', 'size' => '52 x 54 x 82 cm', 'price' => 3400000, 'warranty' => 24, 'stock' => 30, 'sold' => 37, 'tags' => ['Natural', 'Scandinavian'], 'description' => 'A cane-back dining chair with an airy silhouette and a comfortable upholstered seat.'],
                ],
            ],
            [
                'name' => 'Home Office',
                'description' => 'Desks, office chairs, shelves, cabinets, and work-from-home furniture.',
                'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80',
                'products' => [
                    ['name' => 'Atlas Ergonomic Task Chair', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '66 x 66 x 108 cm', 'price' => 6200000, 'warranty' => 36, 'stock' => 25, 'sold' => 78, 'tags' => ['Work-from-home', 'Best Seller'], 'description' => 'An adjustable task chair with lumbar support, breathable mesh, and smooth rolling casters.'],
                    ['name' => 'Rise Electric Standing Desk', 'brand' => 'Furemir', 'color' => 'Natural Oak', 'size' => '140 x 70 x 72-118 cm', 'price' => 14500000, 'warranty' => 48, 'stock' => 14, 'sold' => 55, 'tags' => ['Work-from-home', 'Premium'], 'description' => 'A height-adjustable desk with memory presets for healthier shifts between sitting and standing.'],
                    ['name' => 'Lina Compact Writing Desk', 'brand' => 'NordicNest', 'color' => 'Ivory', 'size' => '110 x 55 x 75 cm', 'price' => 5200000, 'warranty' => 24, 'stock' => 22, 'sold' => 42, 'tags' => ['Compact', 'Scandinavian'], 'description' => 'A compact writing desk with a slim drawer for laptops, notebooks, and daily work tools.'],
                    ['name' => 'Forma Filing Cabinet', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '42 x 52 x 68 cm', 'price' => 3600000, 'warranty' => 24, 'stock' => 20, 'sold' => 31, 'tags' => ['Storage', 'Work-from-home'], 'description' => 'A mobile filing cabinet with lockable drawers for documents and office supplies.'],
                    ['name' => 'Eden Open Bookshelf', 'brand' => 'CasaOak', 'color' => 'Walnut', 'size' => '90 x 35 x 180 cm', 'price' => 8200000, 'warranty' => 30, 'stock' => 16, 'sold' => 39, 'tags' => ['Storage', 'Natural'], 'description' => 'An open walnut bookshelf for books, baskets, and decor in a home office or living room.'],
                    ['name' => 'Nova Meeting Table', 'brand' => 'StudioForm', 'color' => 'Natural Oak', 'size' => '200 x 100 x 75 cm', 'price' => 18200000, 'warranty' => 48, 'stock' => 6, 'sold' => 20, 'tags' => ['Work-from-home', 'Premium'], 'description' => 'A clean meeting table suited to studios, small offices, and collaborative home workspaces.'],
                    ['name' => 'Pixel Desk Riser', 'brand' => 'UrbanWeave', 'color' => 'Sand', 'size' => '80 x 40 x 12 cm', 'price' => 1900000, 'warranty' => 12, 'stock' => 40, 'sold' => 34, 'tags' => ['Compact', 'Work-from-home'], 'description' => 'A desk riser that lifts monitors and creates a tidy shelf for keyboards and accessories.'],
                    ['name' => 'Talon Office Credenza', 'brand' => 'Furemir', 'color' => 'Walnut', 'size' => '150 x 45 x 72 cm', 'price' => 11800000, 'warranty' => 36, 'stock' => 9, 'sold' => 27, 'tags' => ['Storage', 'Work-from-home'], 'description' => 'A low office credenza with closed storage for printers, files, and everyday office clutter.'],
                    ['name' => 'Muse Office Sofa', 'brand' => 'UrbanWeave', 'color' => 'Navy', 'size' => '160 x 78 x 78 cm', 'price' => 9800000, 'warranty' => 30, 'stock' => 11, 'sold' => 23, 'tags' => ['Modern', 'Work-from-home'], 'description' => 'A compact two-seat sofa for office waiting areas, reading breaks, or hybrid work rooms.'],
                    ['name' => 'Kira Drawer Unit', 'brand' => 'NordicNest', 'color' => 'Ivory', 'size' => '36 x 50 x 62 cm', 'price' => 2900000, 'warranty' => 18, 'stock' => 32, 'sold' => 36, 'tags' => ['Storage', 'Compact'], 'description' => 'A small drawer unit that slides under desks for stationery, chargers, and paperwork.'],
                ],
            ],
            [
                'name' => 'Storage',
                'description' => 'Shelving, cabinets, organizers, benches, and compact storage furniture.',
                'image' => 'https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?auto=format&fit=crop&w=900&q=80',
                'products' => [
                    ['name' => 'Grid Modular Shelf', 'brand' => 'StudioForm', 'color' => 'Charcoal', 'size' => '120 x 36 x 180 cm', 'price' => 9200000, 'warranty' => 30, 'stock' => 18, 'sold' => 62, 'tags' => ['Storage', 'Modern', 'Best Seller'], 'description' => 'A modular shelf system with open cubes for books, baskets, and display objects.'],
                    ['name' => 'Pine Shoe Cabinet', 'brand' => 'NordicNest', 'color' => 'Natural Oak', 'size' => '90 x 28 x 105 cm', 'price' => 4800000, 'warranty' => 24, 'stock' => 24, 'sold' => 50, 'tags' => ['Storage', 'Compact'], 'description' => 'A slim shoe cabinet that keeps entryways neat without taking over the hallway.'],
                    ['name' => 'Closet Wardrobe Organizer', 'brand' => 'StudioForm', 'color' => 'Ivory', 'size' => '120 x 45 x 200 cm', 'price' => 8600000, 'warranty' => 30, 'stock' => 12, 'sold' => 29, 'tags' => ['Storage', 'Compact'], 'description' => 'A wardrobe organizer with shelves and rails for flexible closet planning.'],
                    ['name' => 'Harbor Entryway Bench', 'brand' => 'Furemir', 'color' => 'Sage', 'size' => '110 x 40 x 48 cm', 'price' => 5200000, 'warranty' => 24, 'stock' => 20, 'sold' => 32, 'tags' => ['Storage', 'Family'], 'description' => 'An entryway bench with a lift-up seat and lower shelf for bags and everyday shoes.'],
                    ['name' => 'Bergen Tall Bookcase', 'brand' => 'CasaOak', 'color' => 'Walnut', 'size' => '86 x 34 x 190 cm', 'price' => 7600000, 'warranty' => 30, 'stock' => 15, 'sold' => 37, 'tags' => ['Storage', 'Natural'], 'description' => 'A tall bookcase with warm walnut shelves and a narrow footprint for flexible rooms.'],
                    ['name' => 'Float Wall Shelf Set', 'brand' => 'UrbanWeave', 'color' => 'Sand', 'size' => '90 x 22 x 18 cm', 'price' => 2100000, 'warranty' => 12, 'stock' => 42, 'sold' => 45, 'tags' => ['Storage', 'Compact'], 'description' => 'A set of floating shelves for lightweight display and useful vertical storage.'],
                    ['name' => 'Milo Storage Ottoman', 'brand' => 'Furemir', 'color' => 'Terracotta', 'size' => '80 x 45 x 42 cm', 'price' => 3400000, 'warranty' => 18, 'stock' => 26, 'sold' => 40, 'tags' => ['Storage', 'Modern'], 'description' => 'A soft storage ottoman that doubles as a footrest, bench, or hidden blanket box.'],
                    ['name' => 'Aster Pantry Cabinet', 'brand' => 'StudioForm', 'color' => 'Ivory', 'size' => '80 x 45 x 180 cm', 'price' => 7900000, 'warranty' => 30, 'stock' => 10, 'sold' => 22, 'tags' => ['Storage', 'Family'], 'description' => 'A freestanding pantry cabinet with adjustable shelves for kitchen overflow or utility storage.'],
                    ['name' => 'Cube House Organizer', 'brand' => 'UrbanWeave', 'color' => 'Navy', 'size' => '120 x 38 x 120 cm', 'price' => 5900000, 'warranty' => 24, 'stock' => 21, 'sold' => 31, 'tags' => ['Storage', 'Compact'], 'description' => 'A cube organizer for baskets, toys, files, and display pieces across flexible rooms.'],
                    ['name' => 'Linen Bathroom Cabinet', 'brand' => 'NordicNest', 'color' => 'Sage', 'size' => '60 x 35 x 160 cm', 'price' => 6100000, 'warranty' => 24, 'stock' => 14, 'sold' => 28, 'tags' => ['Storage', 'Scandinavian'], 'description' => 'A slim linen cabinet with doors and open shelves for towels and bathroom essentials.'],
                ],
            ],
            [
                'name' => 'Outdoor',
                'description' => 'Weather-ready seating, tables, benches, dining sets, and balcony furniture.',
                'image' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=900&q=80',
                'products' => [
                    ['name' => 'Palma Rattan Sectional', 'brand' => 'PatioHaven', 'color' => 'Sand', 'size' => '260 x 190 x 74 cm', 'price' => 24600000, 'warranty' => 36, 'stock' => 7, 'sold' => 44, 'tags' => ['Outdoor', 'Family', 'Best Seller'], 'description' => 'A weather-ready rattan sectional with washable cushions for patios and covered terraces.'],
                    ['name' => 'Bay Five Piece Patio Dining Set', 'brand' => 'PatioHaven', 'color' => 'Charcoal', 'size' => '150 x 90 x 74 cm', 'price' => 18600000, 'warranty' => 36, 'stock' => 8, 'sold' => 38, 'tags' => ['Outdoor', 'Family'], 'description' => 'A five-piece patio dining set for relaxed outdoor meals with durable, easy-clean surfaces.'],
                    ['name' => 'Cove Adirondack Chair', 'brand' => 'PatioHaven', 'color' => 'Navy', 'size' => '78 x 86 x 94 cm', 'price' => 3900000, 'warranty' => 24, 'stock' => 28, 'sold' => 36, 'tags' => ['Outdoor', 'Compact'], 'description' => 'A classic Adirondack chair with a deep recline and wide arms for outdoor lounging.'],
                    ['name' => 'Teakline Lounge Chair', 'brand' => 'CasaOak', 'color' => 'Natural Oak', 'size' => '72 x 180 x 72 cm', 'price' => 8900000, 'warranty' => 36, 'stock' => 12, 'sold' => 27, 'tags' => ['Outdoor', 'Natural'], 'description' => 'A teak lounge chair with an adjustable back for reading, sunning, or poolside resting.'],
                    ['name' => 'Dune Outdoor Coffee Table', 'brand' => 'PatioHaven', 'color' => 'Sand', 'size' => '100 x 60 x 38 cm', 'price' => 5200000, 'warranty' => 24, 'stock' => 16, 'sold' => 29, 'tags' => ['Outdoor', 'Modern'], 'description' => 'A low outdoor coffee table built for drinks, trays, and easy patio conversation.'],
                    ['name' => 'Willow Garden Bench', 'brand' => 'CasaOak', 'color' => 'Walnut', 'size' => '150 x 60 x 88 cm', 'price' => 7200000, 'warranty' => 30, 'stock' => 10, 'sold' => 24, 'tags' => ['Outdoor', 'Natural'], 'description' => 'A sturdy garden bench with a gently curved back and weather-friendly wood finish.'],
                    ['name' => 'Bistro Balcony Set', 'brand' => 'UrbanWeave', 'color' => 'Terracotta', 'size' => '70 x 70 x 73 cm', 'price' => 6400000, 'warranty' => 24, 'stock' => 18, 'sold' => 33, 'tags' => ['Outdoor', 'Compact'], 'description' => 'A two-chair bistro set scaled for balconies, breakfast corners, and small patios.'],
                    ['name' => 'Sol Shade Patio Umbrella', 'brand' => 'PatioHaven', 'color' => 'Ivory', 'size' => '300 x 300 x 245 cm', 'price' => 4600000, 'warranty' => 18, 'stock' => 20, 'sold' => 26, 'tags' => ['Outdoor', 'Family'], 'description' => 'A broad patio umbrella with a crank lift for simple shade over outdoor dining areas.'],
                    ['name' => 'Ember Fire Pit Table', 'brand' => 'PatioHaven', 'color' => 'Charcoal', 'size' => '110 x 70 x 55 cm', 'price' => 12800000, 'warranty' => 30, 'stock' => 8, 'sold' => 21, 'tags' => ['Outdoor', 'Premium'], 'description' => 'A fire pit table that anchors outdoor seating with warmth and a useful surrounding ledge.'],
                    ['name' => 'Rain Outdoor Storage Box', 'brand' => 'StudioForm', 'color' => 'Sage', 'size' => '130 x 60 x 62 cm', 'price' => 5900000, 'warranty' => 24, 'stock' => 17, 'sold' => 30, 'tags' => ['Outdoor', 'Storage'], 'description' => 'A weather-resistant storage box for cushions, garden tools, and outdoor accessories.'],
                ],
            ],
        ];
    }
}
