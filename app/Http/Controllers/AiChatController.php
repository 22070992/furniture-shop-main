<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    public function reply(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $message = trim($validated['message']);
        $normalized = $this->normalizeText($message);
        $filters = $this->detectFilters($normalized);
        $products = $this->findProducts($filters, $message);

        return response()->json([
            'reply' => $this->buildReply($message, $filters, $products),
            'products' => $products,
            'quick_replies' => $this->quickReplies($filters),
        ]);
    }

    private function findProducts(array $filters, string $message)
    {
        $query = Product::with([
            'category',
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
        } else {
            $query->where(function ($searchQuery) use ($message) {
                $searchQuery->where('name', 'LIKE', '%' . $message . '%')
                    ->orWhere('short_description', 'LIKE', '%' . $message . '%')
                    ->orWhere('description', 'LIKE', '%' . $message . '%')
                    ->orWhere('material', 'LIKE', '%' . $message . '%')
                    ->orWhereHas('category', function ($categoryQuery) use ($message) {
                        $categoryQuery->where('name', 'LIKE', '%' . $message . '%');
                    });
            });
        }

        if ($filters['material']) {
            $query->where('material', 'LIKE', '%' . $filters['material'] . '%');
        }

        $products = $query->orderByDesc('amount_sold')->limit(4)->get();

        if ($products->isEmpty() && !$filters['category'] && !$filters['material'] && !$filters['color'] && !$filters['max_price']) {
            $products = Product::with([
                'category',
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
                'category' => $product->category?->name,
                'material' => $product->material,
                'description' => $product->short_description ?: Str::limit(strip_tags($product->description), 90),
                'price' => (int) ($detail?->original_price ?? 0),
                'color' => $detail?->color?->name,
                'image' => $detail?->images?->first()?->url,
                'url' => url('/products/' . $product->product_id),
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

    private function buildReply(string $message, array $filters, $products): string
    {
        if ($this->isGreeting($this->normalizeText($message))) {
            return 'Chào bạn, mình là trợ lý Furemir. Bạn có thể hỏi mình về ghế văn phòng, ghế treo, ghế gấp, màu sắc, chất liệu hoặc mức giá mong muốn.';
        }

        if ($products->isEmpty()) {
            $categories = Category::orderBy('index')->limit(6)->pluck('name')->implode(', ');

            return 'Mình chưa tìm thấy sản phẩm khớp hoàn toàn. Bạn có thể thử hỏi theo danh mục như: ' . $categories . ', hoặc thêm ngân sách/màu sắc để mình lọc sát hơn.';
        }

        $parts = [];
        if ($filters['category']) {
            $parts[] = $filters['category'];
        }
        if ($filters['color']) {
            $parts[] = 'màu ' . $filters['color'];
        }
        if ($filters['material']) {
            $parts[] = 'chất liệu ' . $filters['material'];
        }
        if ($filters['max_price']) {
            $parts[] = 'dưới ' . number_format($filters['max_price'], 0, '.', ',') . 'đ';
        }

        $context = $parts ? ' phù hợp với ' . implode(', ', $parts) : ' nổi bật trong cửa hàng';

        return 'Mình gợi ý cho bạn vài sản phẩm' . $context . '. Bấm vào từng sản phẩm để xem ảnh, màu và chi tiết trước khi thêm vào giỏ hàng.';
    }

    private function detectFilters(string $normalized): array
    {
        return [
            'category' => $this->detectCategory($normalized),
            'color' => $this->detectColor($normalized),
            'material' => $this->detectMaterial($normalized),
            'max_price' => $this->detectMaxPrice($normalized),
        ];
    }

    private function detectCategory(string $normalized): ?string
    {
        $categoryMap = [
            'Office Chair' => ['office chair', 'ghe van phong', 'van phong', 'lam viec'],
            'Hanging Chair' => ['hanging chair', 'ghe treo', 'xich du', 'thu gian'],
            'Folding Chair' => ['folding chair', 'ghe gap', 'gap gon', 'xep gon'],
        ];

        foreach ($categoryMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($normalized, $keyword)) {
                    return $category;
                }
            }
        }

        foreach (Category::all() as $category) {
            if (Str::contains($normalized, $this->normalizeText($category->name))) {
                return $category->name;
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

    private function isGreeting(string $normalized): bool
    {
        return in_array($normalized, ['hi', 'hello', 'chao', 'xin chao', 'alo'], true);
    }

    private function quickReplies(array $filters): array
    {
        if ($filters['category']) {
            return ['Có màu đen không?', 'Tư vấn dưới 5 triệu', 'Xem chất liệu gỗ'];
        }

        return ['Ghế văn phòng màu đen', 'Ghế treo mây', 'Ghế gấp dưới 3 triệu'];
    }

    private function normalizeText(string $text): string
    {
        return trim(Str::ascii(Str::lower($text)));
    }
}
