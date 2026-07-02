<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Tag;
use App\Services\OpenAiChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AiChatController extends Controller
{
    public function reply(Request $request, OpenAiChatService $openAi)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1000'],
        ]);

        $message = trim($validated['message']);
        $normalized = $this->normalizeText($message);
        $filters = $this->detectFilters($normalized);
        $products = $this->findProducts($filters, $message);
        $history = $validated['history'] ?? [];

        try {
            $reply = $openAi->reply($message, $history, $products, $this->facets());
        } catch (Throwable $exception) {
            report($exception);

            $reply = $openAi->isConfigured()
                ? 'Mình đang gặp lỗi kết nối ChatGPT API nên chưa thể suy nghĩ câu trả lời mới lúc này. Bạn thử lại sau vài giây nhé.'
                : 'Chatbox đã sẵn sàng dùng ChatGPT API, nhưng dự án chưa có OPENAI_API_KEY. Hãy điền key trong file .env rồi chạy php artisan optimize:clear.';
        }

        return response()->json([
            'reply' => $reply,
            'products' => $products,
            'quick_replies' => $this->quickReplies($filters),
        ]);
    }

    private function findProducts(array $filters, string $message)
    {
        $query = Product::with([
            'category',
            'product_type',
            'product_tags.tag',
            'detailed_products' => function ($query) {
                $query->where('is_deleted', 0)
                    ->with(['images', 'color'])
                    ->orderBy('original_price');
            },
        ])
            ->where('is_deleted', false)
            ->whereHas('detailed_products', function ($query) use ($filters) {
                $query->where('is_deleted', 0);

                if ($filters['max_price']) {
                    $query->where('original_price', '<=', $filters['max_price']);
                }

                if ($filters['color']) {
                    $query->whereHas('color', function ($colorQuery) use ($filters) {
                        $colorQuery->where('name', 'LIKE', '%' . $filters['color'] . '%');
                    });
                }
            });

        if ($filters['category']) {
            $query->whereHas('category', function ($categoryQuery) use ($filters) {
                $categoryQuery->where('name', $filters['category']);
            });
        }

        if ($filters['type']) {
            $query->whereHas('product_type', function ($typeQuery) use ($filters) {
                $typeQuery->where('name', $filters['type']);
            });
        }

        if ($filters['tag']) {
            $query->whereHas('product_tags.tag', function ($tagQuery) use ($filters) {
                $tagQuery->where('name', $filters['tag']);
            });
        }

        if (!$filters['category'] && !$filters['type'] && !$filters['tag'] && !$filters['material'] && !$filters['color'] && !$filters['max_price']) {
            $query->where(function ($searchQuery) use ($message) {
                $searchQuery->where('name', 'LIKE', '%' . $message . '%')
                    ->orWhere('short_description', 'LIKE', '%' . $message . '%')
                    ->orWhere('description', 'LIKE', '%' . $message . '%')
                    ->orWhere('material', 'LIKE', '%' . $message . '%')
                    ->orWhereHas('category', function ($categoryQuery) use ($message) {
                        $categoryQuery->where('name', 'LIKE', '%' . $message . '%');
                    })
                    ->orWhereHas('product_type', function ($typeQuery) use ($message) {
                        $typeQuery->where('name', 'LIKE', '%' . $message . '%');
                    })
                    ->orWhereHas('product_tags.tag', function ($tagQuery) use ($message) {
                        $tagQuery->where('name', 'LIKE', '%' . $message . '%');
                    });
            });
        }

        if ($filters['material']) {
            $query->where('material', 'LIKE', '%' . $filters['material'] . '%');
        }

        $products = $query->orderByDesc('amount_sold')->limit(4)->get();

        if ($products->isEmpty() && !$filters['category'] && !$filters['type'] && !$filters['tag'] && !$filters['material'] && !$filters['color'] && !$filters['max_price']) {
            $products = Product::with([
                'category',
                'product_type',
                'product_tags.tag',
                'detailed_products' => function ($query) {
                    $query->where('is_deleted', 0)->with(['images', 'color'])->orderBy('original_price');
                },
            ])
                ->where('is_deleted', false)
                ->has('detailed_products')
                ->orderByDesc('amount_sold')
                ->limit(4)
                ->get();
        }

        return $products->map(function ($product) use ($filters) {
            $detail = $this->bestDetailForProduct($product, $filters);

            return [
                'id' => $product->product_id,
                'name' => $product->name,
                'type' => $product->product_type?->name,
                'category' => $product->category?->name,
                'tags' => $product->product_tags->map(fn ($productTag) => $productTag->tag?->name)->filter()->values(),
                'material' => $product->material,
                'description' => $product->short_description ?: Str::limit(strip_tags($product->description), 90),
                'price' => (int) ($detail?->original_price ?? 0),
                'color' => $detail?->color?->name,
                'image' => $detail?->images?->first()?->url,
                'url' => $product->publicUrl(),
            ];
        })->values();
    }

    private function bestDetailForProduct(Product $product, array $filters)
    {
        $details = $product->detailed_products;

        if ($filters['color']) {
            $matchingColor = $details->first(function ($detail) use ($filters) {
                return Str::contains($this->normalizeText($detail->color?->name ?? ''), $this->normalizeText($filters['color']));
            });

            if ($matchingColor) {
                return $matchingColor;
            }
        }

        if ($filters['max_price']) {
            $underBudget = $details->first(fn ($detail) => $detail->original_price <= $filters['max_price']);

            if ($underBudget) {
                return $underBudget;
            }
        }

        return $details->first();
    }

    private function detectFilters(string $normalized): array
    {
        return [
            'category' => $this->detectCategory($normalized),
            'type' => $this->detectProductType($normalized),
            'tag' => $this->detectTag($normalized),
            'color' => $this->detectColor($normalized),
            'material' => $this->detectMaterial($normalized),
            'max_price' => $this->detectMaxPrice($normalized),
        ];
    }

    private function detectCategory(string $normalized): ?string
    {
        foreach (Category::all() as $category) {
            if (Str::contains($normalized, $this->normalizeText($category->name))) {
                return $category->name;
            }
        }

        return null;
    }

    private function detectProductType(string $normalized): ?string
    {
        $aliases = [
            'Chair' => ['chair', 'ghe', 'ghe van phong', 'ghe treo', 'ghe gap', 'armchair', 'stool'],
            'Table' => ['table', 'ban', 'ban an', 'ban tra', 'coffee table', 'dining table'],
            'Bed' => ['bed', 'giuong', 'nem', 'mattress', 'headboard'],
            'Sofa' => ['sofa', 'ghe sofa'],
            'Wardrobe' => ['wardrobe', 'tu quan ao'],
            'Cabinet & Storage' => ['cabinet', 'tu', 'ke', 'storage', 'dresser', 'chest'],
            'Desk' => ['desk', 'ban lam viec', 'vanity'],
            'Bench' => ['bench', 'ghe dai'],
            'Outdoor Furniture' => ['outdoor', 'ngoai troi', 'san vuon', 'ban cong'],
        ];

        foreach ($aliases as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalized, $keyword)) {
                    return $type;
                }
            }
        }

        foreach (ProductType::all() as $type) {
            if (Str::contains($normalized, $this->normalizeText($type->name))) {
                return $type->name;
            }
        }

        return null;
    }

    private function detectTag(string $normalized): ?string
    {
        foreach (Tag::all() as $tag) {
            if (Str::contains($normalized, $this->normalizeText($tag->name))) {
                return $tag->name;
            }
        }

        return null;
    }

    private function detectColor(string $normalized): ?string
    {
        $colors = [
            'Black' => ['black', 'den'],
            'White' => ['white', 'trang'],
            'Gray' => ['gray', 'grey', 'xam'],
            'Brown' => ['brown', 'nau'],
            'Beige' => ['beige', 'cream', 'kem', 'be'],
            'Blue' => ['blue', 'xanh duong'],
            'Green' => ['green', 'xanh la'],
            'Red' => ['red', 'do'],
            'Yellow' => ['yellow', 'vang'],
        ];

        foreach ($colors as $color => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalized, $keyword)) {
                    return $color;
                }
            }
        }

        return null;
    }

    private function detectMaterial(string $normalized): ?string
    {
        $materials = [
            'wood' => ['wood', 'go'],
            'rattan' => ['rattan', 'may', 'dan'],
            'leather' => ['leather', 'da'],
            'fabric' => ['fabric', 'vai', 'ni'],
            'metal' => ['metal', 'kim loai', 'sat', 'thep'],
        ];

        foreach ($materials as $material => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalized, $keyword)) {
                    return $material;
                }
            }
        }

        return null;
    }

    private function detectMaxPrice(string $normalized): ?int
    {
        if (!preg_match('/(?:duoi|toi da|khoang|under|max|<=)?\s*([0-9]+(?:[\\.,][0-9]+)?)\s*(trieu|tr|m|k|nghin|ngan)?/u', $normalized, $matches)) {
            return null;
        }

        $amount = (float) str_replace(',', '.', $matches[1]);
        $unit = $matches[2] ?? '';

        return match ($unit) {
            'trieu', 'tr', 'm' => (int) ($amount * 1000000),
            'k', 'nghin', 'ngan' => (int) ($amount * 1000),
            default => $amount < 1000 ? (int) ($amount * 1000000) : (int) $amount,
        };
    }

    private function facets(): array
    {
        return [
            'rooms' => Category::orderBy('index')->limit(12)->pluck('name')->values(),
            'product_types' => ProductType::orderBy('index')->limit(12)->pluck('name')->values(),
            'theme_tags' => Tag::orderBy('name')->limit(20)->pluck('name')->values(),
        ];
    }

    private function quickReplies(array $filters): array
    {
        if ($filters['type']) {
            return [
                'Loại này có màu sáng không?',
                'Gợi ý dưới 5 triệu',
                'Phù hợp phòng nhỏ không?',
            ];
        }

        return ProductType::orderBy('index')->limit(3)->pluck('name')
            ->map(fn ($type) => 'Tư vấn ' . Str::lower($type))
            ->values()
            ->all() ?: ['Tư vấn bàn cho phòng khách', 'Tư vấn ghế làm việc', 'Tư vấn giường phòng ngủ'];
    }

    private function normalizeText(string $text): string
    {
        return trim(Str::ascii(Str::lower($text)));
    }
}
