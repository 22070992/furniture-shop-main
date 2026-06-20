<?php

namespace App\Services;

use App\Models\AiComboSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class AiInteriorComboService
{
    public function __construct(
        private readonly ProductCatalogPresenter $presenter,
        private readonly AdminAiService $adminAi,
    ) {
    }

    public function recommend(array $input): array
    {
        $settings = $this->settings();
        $products = $this->candidateProducts($input, $settings);
        $warnings = [];

        if ($products->isEmpty()) {
            return $this->emptyResponse($input, $settings, ['No active in-stock product matched this brief.']);
        }

        $source = 'fallback';
        $selection = [];
        $aiNotes = [];

        if (($settings['enabled'] ?? true) === true) {
            try {
                $aiResponse = $this->adminAi->interiorComboRecommendation(
                    $this->cleanInput($input),
                    $this->candidatePayload($products),
                    $settings
                );
                [$selection, $aiNotes] = $this->normalizeAiSelection($aiResponse);
                $source = 'ai';
            } catch (Throwable $exception) {
                $warnings[] = 'AI provider failed, so Furemir used the rule-based fallback.';
            }
        } else {
            $warnings[] = 'AI combo is disabled by admin settings, so Furemir used the rule-based fallback.';
        }

        $verified = $this->verifySelection($selection, $products, $input, $settings, $warnings);

        if ($verified->count() < $this->minProducts($settings)) {
            $fallback = $this->fallbackSelection($products, $input, $settings, $verified->pluck('product_id')->all());
            $verified = $verified
                ->merge($this->verifySelection($fallback, $products, $input, $settings, $warnings))
                ->unique('product_id')
                ->take($this->maxProducts($settings))
                ->values();

            if ($source === 'ai') {
                $warnings[] = 'AI selection was incomplete or invalid, so fallback products were added.';
            }
            $source = $source === 'ai' && $verified->isNotEmpty() ? 'ai+fallback' : 'fallback';
        }

        return $this->response($input, $settings, $verified, $source, array_values(array_unique($warnings)), $aiNotes);
    }

    public function cartItems(array $items): array
    {
        $settings = $this->settings();
        $max = $this->maxProducts($settings);
        $skipped = [];
        $cartItems = [];
        $seen = [];

        foreach (array_slice($items, 0, $max) as $item) {
            $sku = (string) ($item['sku'] ?? '');
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($sku === '' || $productId <= 0 || isset($seen[$sku])) {
                continue;
            }

            $detail = ProductDetail::with(['product', 'images', 'color', 'product_discounts.discount'])
                ->where('sku', $sku)
                ->where('product_id', $productId)
                ->where('is_deleted', 0)
                ->first();

            if (!$detail || !$detail->product || $detail->product->is_deleted) {
                $skipped[] = ['product_id' => $productId, 'sku' => $sku, 'reason' => 'Product or SKU is not available.'];
                continue;
            }

            if ($detail->quantities <= 0) {
                $skipped[] = ['product_id' => $productId, 'sku' => $sku, 'reason' => 'SKU is out of stock.'];
                continue;
            }

            if ($quantity > $detail->quantities) {
                $skipped[] = [
                    'product_id' => $productId,
                    'sku' => $sku,
                    'reason' => 'Requested quantity was reduced to available stock.',
                ];
                $quantity = (int) $detail->quantities;
            }

            $cartItems[] = [
                'product_id' => $productId,
                'sku' => $detail->sku,
                'quantities' => $quantity,
                'color_name' => $detail->color?->name,
                'color_hex' => $detail->color?->code,
                'image' => $detail->images?->first()?->url,
                'unit_price' => $this->presenter->discountedPrice($detail),
            ];

            $seen[$sku] = true;
        }

        return ['items' => $cartItems, 'skipped' => $skipped];
    }

    public function settings(): array
    {
        $settings = AiComboSetting::resolved();

        $settings['max_products'] = min(5, max(3, (int) ($settings['max_products'] ?? 5)));
        $settings['min_products'] = min($settings['max_products'], max(1, (int) ($settings['min_products'] ?? 3)));
        $settings['candidate_limit'] = min(60, max($settings['max_products'], (int) ($settings['candidate_limit'] ?? 24)));
        $settings['budget_overage_percent'] = max(0, min(50, (float) ($settings['budget_overage_percent'] ?? 10)));
        $settings['room_category_mapping'] = is_array($settings['room_category_mapping'] ?? null)
            ? $settings['room_category_mapping']
            : [];

        return $settings;
    }

    private function candidateProducts(array $input, array $settings): Collection
    {
        $limit = $settings['candidate_limit'];
        $query = $this->presenter->activeProductQuery()
            ->whereHas('detailed_products', function ($query) {
                $query->where('is_deleted', 0)->where('quantities', '>', 0);
            });

        if (!empty($input['product_id'])) {
            $query->where('product_id', '<>', (int) $input['product_id']);
        }

        $products = $this->presenter->decorateProducts($query->take(max(80, $limit * 5))->get());

        return $products
            ->map(function (Product $product) use ($input, $settings) {
                $product->ai_combo_score = $this->scoreProduct($product, $input, $settings);

                return $product;
            })
            ->filter(fn (Product $product) => $product->presentation->stock_total > 0)
            ->sortByDesc('ai_combo_score')
            ->take($limit)
            ->values();
    }

    private function scoreProduct(Product $product, array $input, array $settings): float
    {
        $text = $this->normalized($product->name . ' ' . $product->category?->name . ' ' . $product->material . ' ' . $product->description);
        $room = $this->normalized((string) ($input['room'] ?? ''));
        $style = $this->normalized((string) ($input['style'] ?? ''));
        $color = $this->normalized((string) ($input['color'] ?? ''));
        $freeText = $this->normalized((string) ($input['prompt'] ?? ''));
        $categoryName = $this->normalized($product->category?->name ?? '');
        $score = 0.0;

        foreach ($this->targetCategoriesForRoom($room, $settings) as $target) {
            $targetText = $this->normalized($target);
            if ($targetText !== '' && (str_contains($categoryName, $targetText) || str_contains($text, $targetText))) {
                $score += 45;
                break;
            }
        }

        if ($room !== '' && str_contains($text, $room)) {
            $score += 20;
        }

        if ($style !== '' && str_contains($text, $style)) {
            $score += 12;
        }

        if ($freeText !== '') {
            foreach (collect(explode(' ', $freeText))->filter(fn ($word) => strlen($word) >= 4)->take(8) as $word) {
                if (str_contains($text, $word)) {
                    $score += 3;
                }
            }
        }

        if ($color !== '') {
            $hasColor = $product->detailed_products
                ->contains(fn ($detail) => str_contains($this->normalized($detail->color?->name ?? ''), $color));
            $score += $hasColor ? 16 : 0;
        }

        if (!empty($input['budget'])) {
            $budget = (float) $input['budget'];
            $price = (float) $product->presentation->min_price;
            if ($price > 0 && $price <= $budget) {
                $score += max(0, 18 - (($price / max(1, $budget)) * 8));
            } elseif ($price > $budget) {
                $score -= min(25, (($price - $budget) / max(1, $budget)) * 30);
            }
        }

        $score += min(10, $product->presentation->rating_average * 2);
        $score += min(8, $product->presentation->review_count);
        $score += $product->presentation->image ? 4 : 0;
        $score += min(6, $product->presentation->stock_total / 5);

        return round($score, 2);
    }

    private function candidatePayload(Collection $products): array
    {
        return $products->map(function (Product $product) {
            return [
                'product_id' => (int) $product->product_id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'material' => $product->material,
                'rating' => $product->presentation->rating_average,
                'review_count' => $product->presentation->review_count,
                'min_price' => (float) $product->presentation->min_price,
                'stock_total' => (int) $product->presentation->stock_total,
                'skus' => $this->stockedDetails($product)->map(fn ($detail) => [
                    'sku' => $detail->sku,
                    'color' => $detail->color?->name,
                    'size' => $detail->size,
                    'price' => $this->presenter->discountedPrice($detail),
                    'stock' => (int) $detail->quantities,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    private function normalizeAiSelection(mixed $response): array
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return [[], ['AI returned invalid JSON.']];
            }
            $response = $decoded;
        }

        if (!is_array($response)) {
            return [[], ['AI returned an invalid payload.']];
        }

        $combo = $response['combo'] ?? $response['products'] ?? [];
        if (!is_array($combo)) {
            return [[], ['AI returned an invalid combo list.']];
        }

        return [$combo, collect($response['notes'] ?? [])->filter()->values()->all()];
    }

    private function verifySelection(array $selection, Collection $products, array $input, array $settings, array &$warnings): Collection
    {
        $verified = collect();
        $seen = [];
        $budget = isset($input['budget']) ? (float) $input['budget'] : null;
        $maxAllowed = $budget ? $budget * (1 + ((float) $settings['budget_overage_percent'] / 100)) : null;

        foreach ($selection as $selectionItem) {
            $productId = (int) ($selectionItem['product_id'] ?? 0);
            if ($productId <= 0 || isset($seen[$productId])) {
                continue;
            }

            $product = $products->firstWhere('product_id', $productId);
            if (!$product) {
                if ($productId > 0) {
                    $warnings[] = 'AI selected product #' . $productId . ', but it is not an active in-stock candidate.';
                }
                continue;
            }

            $detail = $this->chooseDetail($product, (string) ($selectionItem['sku'] ?? ''), (string) ($input['color'] ?? ''));
            if (!$detail) {
                $warnings[] = $product->name . ' was skipped because no active in-stock SKU was available.';
                continue;
            }

            $price = $this->presenter->discountedPrice($detail);
            $newTotal = $verified->sum('price') + $price;
            if ($maxAllowed && $verified->count() >= $this->minProducts($settings) && $newTotal > $maxAllowed) {
                $warnings[] = $product->name . ' was skipped to keep the combo close to budget.';
                continue;
            }

            $verified->push($this->productPayload(
                $product,
                $detail,
                $selectionItem['role'] ?? $this->roleForProduct($product),
                $selectionItem['reason'] ?? $this->reasonForProduct($product, $input)
            ));
            $seen[$productId] = true;

            if ($verified->count() >= $this->maxProducts($settings)) {
                break;
            }
        }

        return $verified;
    }

    private function fallbackSelection(Collection $products, array $input, array $settings, array $excludeProductIds = []): array
    {
        $exclude = collect($excludeProductIds)->map(fn ($id) => (int) $id)->all();
        $selected = [];
        $usedCategories = [];

        foreach ($products as $product) {
            if (in_array((int) $product->product_id, $exclude, true)) {
                continue;
            }

            $categoryKey = $this->normalized($product->category?->name ?? 'other');
            if (isset($usedCategories[$categoryKey]) && count($selected) < $this->minProducts($settings)) {
                continue;
            }

            $detail = $this->chooseDetail($product, '', (string) ($input['color'] ?? ''));
            if (!$detail) {
                continue;
            }

            $selected[] = [
                'product_id' => (int) $product->product_id,
                'sku' => $detail->sku,
                'role' => $this->roleForProduct($product),
                'reason' => $this->reasonForProduct($product, $input),
            ];
            $usedCategories[$categoryKey] = true;

            if (count($selected) >= $this->maxProducts($settings)) {
                break;
            }
        }

        if (count($selected) < $this->minProducts($settings)) {
            foreach ($products as $product) {
                if (in_array((int) $product->product_id, $exclude, true)
                    || collect($selected)->contains('product_id', (int) $product->product_id)) {
                    continue;
                }

                $detail = $this->chooseDetail($product, '', (string) ($input['color'] ?? ''));
                if (!$detail) {
                    continue;
                }

                $selected[] = [
                    'product_id' => (int) $product->product_id,
                    'sku' => $detail->sku,
                    'role' => $this->roleForProduct($product),
                    'reason' => $this->reasonForProduct($product, $input),
                ];

                if (count($selected) >= $this->maxProducts($settings)) {
                    break;
                }
            }
        }

        return $selected;
    }

    private function response(array $input, array $settings, Collection $products, string $source, array $warnings, array $aiNotes): array
    {
        $total = (float) $products->sum('price');
        $budget = isset($input['budget']) ? (float) $input['budget'] : null;
        $overBudget = $budget !== null && $budget > 0 && $total > $budget;

        if ($overBudget) {
            $warnings[] = 'Combo is over budget by ' . number_format($total - $budget, 0, '.', ',') . 'đ.';
        }

        return [
            'source' => $source,
            'model' => $settings['model'] ?? 'furemir-rule-assisted',
            'products' => $products->values()->all(),
            'total_price' => $total,
            'formatted_total' => number_format($total, 0, '.', ',') . 'đ',
            'budget' => $budget,
            'formatted_budget' => $budget ? number_format($budget, 0, '.', ',') . 'đ' : null,
            'over_budget' => $overBudget,
            'warnings' => array_values(array_unique($warnings)),
            'notes' => $aiNotes,
            'settings' => [
                'max_products' => $this->maxProducts($settings),
                'budget_overage_percent' => (float) $settings['budget_overage_percent'],
            ],
        ];
    }

    private function emptyResponse(array $input, array $settings, array $warnings): array
    {
        return $this->response($input, $settings, collect(), 'fallback', $warnings, []);
    }

    private function productPayload(Product $product, ProductDetail $detail, string $role, string $reason): array
    {
        $image = $detail->images?->first()?->url ?: $product->presentation->image;
        $price = $this->presenter->discountedPrice($detail);

        return [
            'product_id' => (int) $product->product_id,
            'sku' => $detail->sku,
            'name' => $product->name,
            'category' => $product->category?->name ?? 'Furniture',
            'material' => $product->material,
            'size' => $detail->size,
            'color_name' => $detail->color?->name,
            'color_hex' => $detail->color?->code,
            'image' => $image,
            'url' => method_exists($product, 'publicUrl') ? $product->publicUrl() : url('/products/' . $product->product_id),
            'price' => $price,
            'formatted_price' => number_format($price, 0, '.', ',') . 'đ',
            'stock' => (int) $detail->quantities,
            'rating_average' => (float) $product->presentation->rating_average,
            'review_count' => (int) $product->presentation->review_count,
            'role' => Str::limit(strip_tags($role), 80, ''),
            'reason' => Str::limit(strip_tags($reason), 180, ''),
        ];
    }

    private function chooseDetail(Product $product, string $sku = '', string $preferredColor = ''): ?ProductDetail
    {
        $details = $this->stockedDetails($product, $preferredColor);

        if ($sku !== '') {
            $selected = $details->firstWhere('sku', $sku);
            if ($selected) {
                return $selected;
            }
        }

        return $details->first();
    }

    private function stockedDetails(Product $product, string $preferredColor = ''): Collection
    {
        $color = $this->normalized($preferredColor);

        return $product->detailed_products
            ->filter(fn ($detail) => !$detail->is_deleted && (int) $detail->quantities > 0)
            ->sortByDesc(function ($detail) use ($color) {
                $colorScore = $color !== '' && str_contains($this->normalized($detail->color?->name ?? ''), $color) ? 1000 : 0;

                return $colorScore + (int) $detail->quantities;
            })
            ->values();
    }

    private function targetCategoriesForRoom(string $room, array $settings): array
    {
        $mapping = $settings['room_category_mapping'] ?? [];
        if ($room === '') {
            return collect($mapping)->flatten()->unique()->values()->all();
        }

        foreach ($mapping as $roomKey => $categories) {
            $normalizedRoomKey = $this->normalized((string) $roomKey);
            if ($normalizedRoomKey !== '' && (str_contains($room, $normalizedRoomKey) || str_contains($normalizedRoomKey, $room))) {
                return (array) $categories;
            }
        }

        return Category::query()
            ->where('name', 'like', '%' . $room . '%')
            ->pluck('name')
            ->all();
    }

    private function roleForProduct(Product $product): string
    {
        $category = $product->category?->name ?? 'Furniture';

        return match (true) {
            Str::contains(Str::lower($category), ['chair', 'sofa', 'bed']) => 'Main comfort piece',
            Str::contains(Str::lower($category), ['table', 'desk']) => 'Functional surface',
            Str::contains(Str::lower($category), ['lamp', 'light']) => 'Lighting layer',
            Str::contains(Str::lower($category), ['rug']) => 'Texture and zoning',
            Str::contains(Str::lower($category), ['cabinet', 'wardrobe', 'shelf', 'bookcase']) => 'Storage and organization',
            default => 'Room accent',
        };
    }

    private function reasonForProduct(Product $product, array $input): string
    {
        $parts = [];
        if ($product->category?->name) {
            $parts[] = 'adds a ' . Str::lower($product->category->name) . ' layer';
        }
        if ($product->material) {
            $parts[] = 'uses ' . Str::lower($product->material);
        }
        if (!empty($input['style'])) {
            $parts[] = 'fits the ' . Str::lower((string) $input['style']) . ' direction';
        }

        return ucfirst(implode(', ', array_slice($parts, 0, 3))) . '.';
    }

    private function cleanInput(array $input): array
    {
        return collect($input)
            ->only(['room', 'budget', 'style', 'color', 'dimensions', 'prompt'])
            ->map(fn ($value) => is_string($value) ? Str::limit(strip_tags($value), 1000, '') : $value)
            ->all();
    }

    private function normalized(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::ascii(Str::lower($value))));
    }

    private function minProducts(array $settings): int
    {
        return (int) ($settings['min_products'] ?? 3);
    }

    private function maxProducts(array $settings): int
    {
        return (int) ($settings['max_products'] ?? 5);
    }
}
