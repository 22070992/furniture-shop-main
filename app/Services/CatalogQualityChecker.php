<?php

namespace App\Services;

use App\Models\CatalogQualityIssue;
use App\Models\CatalogQualityRun;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CatalogQualityChecker
{
    public function __construct(private readonly AdminAiService $adminAi)
    {
    }

    public function run(User $admin, array $options): CatalogQualityRun
    {
        $scanType = $options['scan_type'] ?? CatalogQualityRun::SCAN_RULE;
        $run = CatalogQualityRun::create([
            'started_by' => $admin->getKey(),
            'status' => CatalogQualityRun::STATUS_RUNNING,
            'scan_type' => $scanType,
            'started_at' => now(),
        ]);

        $products = $this->productsForScan($options);
        $categoryPriceStats = $this->categoryPriceStats();
        $duplicateNames = $this->duplicateNameMap();
        $seen = [];
        $errorMessages = [];

        if ($scanType === CatalogQualityRun::SCAN_AI) {
            $this->storeIssue($run, [
                'issue_code' => 'AI_VISION_UNAVAILABLE',
                'severity' => 'info',
                'source' => CatalogQualityIssue::SOURCE_AI,
                'field_name' => 'image',
                'current_value' => null,
                'suggestion' => 'Current Furemir AI provider supports text catalog assistance only. Image/name mismatch vision analysis is not available.',
                'metadata' => ['provider' => 'local-admin-ai-service', 'vision' => false],
            ], $seen);
        }

        foreach ($products as $product) {
            foreach ($this->ruleIssues($product, $categoryPriceStats, $duplicateNames) as $issue) {
                $this->storeIssue($run, $issue, $seen);
            }

            if ($scanType !== CatalogQualityRun::SCAN_AI) {
                continue;
            }

            try {
                foreach ($this->normalizeAiIssues($this->adminAi->catalogQualityTextReview($product), $product) as $issue) {
                    $this->storeIssue($run, $issue, $seen);
                }
            } catch (Throwable $exception) {
                $errorMessages[] = 'Product #' . $product->product_id . ': ' . $exception->getMessage();
                $this->storeIssue($run, [
                    'product_id' => $product->product_id,
                    'issue_code' => 'AI_PRODUCT_SCAN_FAILED',
                    'severity' => 'low',
                    'source' => CatalogQualityIssue::SOURCE_AI,
                    'field_name' => null,
                    'current_value' => null,
                    'suggestion' => 'AI text scan failed for this product. Rule-based checks were still completed.',
                    'metadata' => ['error' => Str::limit($exception->getMessage(), 500)],
                ], $seen);
            }
        }

        $run->update([
            'status' => CatalogQualityRun::STATUS_COMPLETED,
            'total_products' => $products->count(),
            'total_issues' => $run->issues()->count(),
            'completed_at' => now(),
            'error_message' => empty($errorMessages) ? null : implode("\n", array_slice($errorMessages, 0, 10)),
        ]);

        return $run->refresh();
    }

    public function rerunProduct(User $admin, Product $product, string $scanType): CatalogQualityRun
    {
        return $this->run($admin, [
            'scan_type' => $scanType,
            'scope' => 'product',
            'product_id' => $product->product_id,
        ]);
    }

    public function updateIssueStatus(CatalogQualityIssue $issue, string $status): CatalogQualityIssue
    {
        $issue->update(['status' => $status]);

        return $issue->refresh();
    }

    public function applySuggestion(CatalogQualityIssue $issue): CatalogQualityIssue
    {
        abort_unless($issue->canApplySuggestion(), 422, 'This suggestion cannot be applied safely.');

        $field = $issue->field_name;
        abort_if(in_array($field, ['original_price', 'price', 'quantities', 'stock'], true), 422, 'AI cannot apply price or stock changes.');

        DB::transaction(function () use ($issue, $field) {
            $product = Product::lockForUpdate()->findOrFail($issue->product_id);
            $product->{$field} = $issue->suggestion;
            $product->save();

            $issue->update(['status' => CatalogQualityIssue::STATUS_RESOLVED]);
        });

        return $issue->refresh();
    }

    private function productsForScan(array $options): Collection
    {
        $limit = min(
            (int) ($options['limit'] ?? config('shop.catalog_quality.max_scan_products', 100)),
            (int) config('shop.catalog_quality.max_scan_products', 100)
        );

        return Product::query()
            ->with([
                'category',
                'brand',
                'detailed_products.images',
                'detailed_products.color',
            ])
            ->when(($options['scope'] ?? 'all') === 'product' && !empty($options['product_id']), function (Builder $query) use ($options) {
                $query->where('product_id', (int) $options['product_id']);
            })
            ->when(($options['scope'] ?? 'all') === 'category' && !empty($options['category_id']), function (Builder $query) use ($options) {
                $query->where('category_id', (int) $options['category_id']);
            })
            ->when(($options['status'] ?? 'all') === 'active', fn (Builder $query) => $query->where('is_deleted', 0))
            ->when(($options['status'] ?? 'all') === 'inactive', fn (Builder $query) => $query->where('is_deleted', 1))
            ->orderBy('product_id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function ruleIssues(Product $product, Collection $categoryPriceStats, Collection $duplicateNames): array
    {
        $issues = [];
        $activeSkus = $product->detailed_products->filter(fn ($detail) => !(bool) $detail->is_deleted)->values();
        $allImagesCount = $activeSkus->sum(fn ($detail) => $detail->images->count());
        $description = trim(strip_tags((string) $product->description));
        $minImages = (int) config('shop.catalog_quality.min_images_per_product', 2);
        $minDescriptionChars = (int) config('shop.catalog_quality.min_description_chars', 90);
        $lowStock = (int) config('shop.catalog_quality.low_stock_threshold', 5);
        $normalizedName = $this->normalizeText($product->name);

        if ($allImagesCount === 0) {
            $issues[] = $this->issue($product, null, 'PRODUCT_MISSING_IMAGES', 'critical', 'image', null, 'Add at least one real product image.');
        } elseif ($allImagesCount < $minImages) {
            $issues[] = $this->issue($product, null, 'PRODUCT_NEEDS_MORE_IMAGES', 'medium', 'image', (string) $allImagesCount, 'Add more product images for color/detail coverage.', [
                'min_images' => $minImages,
            ]);
        }

        if ($description === '') {
            $issues[] = $this->issue($product, null, 'PRODUCT_MISSING_DESCRIPTION', 'high', 'description', null, 'Add a concise product description with material, room fit and use case.');
        } elseif (Str::length($description) < $minDescriptionChars) {
            $issues[] = $this->issue($product, null, 'PRODUCT_DESCRIPTION_TOO_SHORT', 'medium', 'description', $description, 'Expand the description with benefits, material and dimensions.', [
                'min_chars' => $minDescriptionChars,
                'current_chars' => Str::length($description),
            ]);
        }

        if (!$product->category_id || !$product->category) {
            $issues[] = $this->issue($product, null, 'PRODUCT_MISSING_CATEGORY', 'high', 'category_id', (string) $product->category_id, 'Assign a category or room collection.');
        }

        if (!filled($product->material)) {
            $issues[] = $this->issue($product, null, 'PRODUCT_MISSING_MATERIAL', 'medium', 'material', null, 'Add material information for filtering and comparison.');
        }

        if ($activeSkus->isEmpty()) {
            $issues[] = $this->issue($product, null, 'PRODUCT_HAS_NO_SKU', 'critical', 'sku', null, 'Create at least one active SKU/variant.');
        }

        if ($activeSkus->isNotEmpty() && $activeSkus->every(fn ($detail) => !filled($detail->size))) {
            $issues[] = $this->issue($product, null, 'PRODUCT_MISSING_DIMENSIONS', 'medium', 'size', null, 'Add dimensions on at least one SKU.');
        }

        if ((bool) $product->is_deleted && $activeSkus->isNotEmpty()) {
            $issues[] = $this->issue($product, null, 'INACTIVE_PRODUCT_HAS_ACTIVE_SKUS', 'low', 'is_deleted', 'inactive', 'Review whether active SKUs should also be hidden.');
        }

        if ($duplicateNames->get($normalizedName, 0) > 1) {
            $issues[] = $this->issue($product, null, 'SUSPECT_DUPLICATE_PRODUCT_NAME', 'medium', 'name', $product->name, 'Review duplicate product naming to avoid catalog confusion.', [
                'duplicate_count' => $duplicateNames->get($normalizedName),
            ]);
        }

        if (Schema::hasColumn('products', 'slug')) {
            $slugCount = Product::where('slug', $product->slug)->where('product_id', '<>', $product->product_id)->count();
            if ($product->slug && $slugCount > 0) {
                $issues[] = $this->issue($product, null, 'DUPLICATE_PRODUCT_SLUG', 'critical', 'slug', $product->slug, 'Generate a unique product slug.');
            }
        }

        if ($this->ruleLooksCategoryMismatch($product->name, $product->category?->name)) {
            $issues[] = $this->issue($product, null, 'POSSIBLE_NAME_CATEGORY_MISMATCH', 'high', 'category_id', $product->category?->name, 'Review product name, category and attributes for consistency.');
        }

        foreach ($activeSkus as $detail) {
            $issues = array_merge($issues, $this->skuIssues($product, $detail, $categoryPriceStats, $lowStock));
        }

        return $issues;
    }

    private function skuIssues(Product $product, ProductDetail $detail, Collection $categoryPriceStats, int $lowStock): array
    {
        $issues = [];

        if (!$detail->color_id || !$detail->color) {
            $issues[] = $this->issue($product, $detail, 'SKU_MISSING_COLOR', 'high', 'color_id', (string) $detail->color_id, 'Assign a color to this SKU.');
        }

        if ($detail->images->isEmpty()) {
            $issues[] = $this->issue($product, $detail, 'SKU_MISSING_IMAGE', 'high', 'image', null, 'Add an image that matches this SKU color/variant.');
        }

        if (!filled($detail->name) || !filled($detail->description) || !filled($detail->size)) {
            $issues[] = $this->issue($product, $detail, 'SKU_INCOMPLETE_VARIANT_DATA', 'medium', 'variant', json_encode([
                'name' => $detail->name,
                'description' => Str::limit(strip_tags((string) $detail->description), 120),
                'size' => $detail->size,
            ], JSON_UNESCAPED_UNICODE), 'Complete variant name, description and dimensions.');
        }

        if ((float) $detail->original_price === 0.0) {
            $issues[] = $this->issue($product, $detail, 'SKU_PRICE_ZERO', 'critical', 'original_price', (string) $detail->original_price, 'Set a valid selling price.');
        } elseif ((float) $detail->original_price < 0) {
            $issues[] = $this->issue($product, $detail, 'SKU_PRICE_NEGATIVE', 'critical', 'original_price', (string) $detail->original_price, 'Price cannot be negative.');
        } else {
            $priceIssue = $this->priceOutlierIssue($product, $detail, $categoryPriceStats);
            if ($priceIssue) {
                $issues[] = $priceIssue;
            }
        }

        if ((int) $detail->quantities === 0) {
            $issues[] = $this->issue($product, $detail, 'SKU_OUT_OF_STOCK', 'high', 'quantities', '0', 'Restock or hide this SKU before promoting the product.');
        } elseif ((int) $detail->quantities <= $lowStock) {
            $issues[] = $this->issue($product, $detail, 'SKU_LOW_STOCK', 'medium', 'quantities', (string) $detail->quantities, 'Restock soon or reduce promotion pressure.', [
                'threshold' => $lowStock,
            ]);
        }

        return $issues;
    }

    private function priceOutlierIssue(Product $product, ProductDetail $detail, Collection $categoryPriceStats): ?array
    {
        $stats = $categoryPriceStats->get((int) $product->category_id);
        $sampleMin = (int) config('shop.catalog_quality.price_outlier_min_sample', 3);

        if (!$stats || $stats['count'] < $sampleMin || $stats['average'] <= 0) {
            return null;
        }

        $multiplier = (float) config('shop.catalog_quality.price_outlier_multiplier', 3);
        $price = (float) $detail->original_price;
        $average = (float) $stats['average'];

        if ($price > $average * $multiplier || $price < $average / $multiplier) {
            return $this->issue($product, $detail, 'SKU_PRICE_CATEGORY_OUTLIER', 'medium', 'original_price', (string) $detail->original_price, 'Review whether this price is intentional for the category.', [
                'category_average' => round($average, 2),
                'multiplier' => $multiplier,
            ]);
        }

        return null;
    }

    private function normalizeAiIssues(array $aiIssues, Product $product): array
    {
        $normalized = [];

        foreach ($aiIssues as $issue) {
            if (!is_array($issue) || empty($issue['issue_code']) || empty($issue['severity'])) {
                $normalized[] = [
                    'product_id' => $product->product_id,
                    'issue_code' => 'AI_INVALID_RESPONSE',
                    'severity' => 'low',
                    'source' => CatalogQualityIssue::SOURCE_AI,
                    'field_name' => null,
                    'current_value' => null,
                    'suggestion' => 'AI returned an invalid issue payload and it was ignored safely.',
                    'metadata' => ['payload' => is_scalar($issue) ? (string) $issue : 'non-scalar payload'],
                ];
                continue;
            }

            $normalized[] = [
                'product_id' => $product->product_id,
                'sku' => $issue['sku'] ?? null,
                'issue_code' => (string) $issue['issue_code'],
                'severity' => $this->validSeverity((string) $issue['severity']),
                'source' => CatalogQualityIssue::SOURCE_AI,
                'field_name' => $issue['field_name'] ?? null,
                'current_value' => $issue['current_value'] ?? null,
                'suggestion' => $issue['suggestion'] ?? null,
                'metadata' => $issue['metadata'] ?? [],
            ];
        }

        return $normalized;
    }

    private function issue(Product $product, ?ProductDetail $detail, string $code, string $severity, ?string $field, ?string $currentValue, ?string $suggestion, array $metadata = []): array
    {
        return [
            'product_id' => $product->product_id,
            'sku' => $detail?->sku,
            'issue_code' => $code,
            'severity' => $severity,
            'source' => CatalogQualityIssue::SOURCE_RULE,
            'field_name' => $field,
            'current_value' => $currentValue,
            'suggestion' => $suggestion,
            'metadata' => $metadata,
        ];
    }

    private function storeIssue(CatalogQualityRun $run, array $issue, array &$seen): void
    {
        $key = implode('|', [
            $issue['product_id'] ?? 'run',
            $issue['sku'] ?? 'product',
            $issue['issue_code'] ?? 'unknown',
            $issue['source'] ?? 'rule',
            $issue['field_name'] ?? 'field',
        ]);

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $issue['run_id'] = $run->id;
        $issue['severity'] = $this->validSeverity((string) ($issue['severity'] ?? 'low'));
        $issue['source'] = in_array(($issue['source'] ?? 'rule'), [CatalogQualityIssue::SOURCE_RULE, CatalogQualityIssue::SOURCE_AI], true)
            ? $issue['source']
            : CatalogQualityIssue::SOURCE_RULE;
        $issue['status'] = CatalogQualityIssue::STATUS_OPEN;

        CatalogQualityIssue::create($issue);
    }

    private function categoryPriceStats(): Collection
    {
        return ProductDetail::query()
            ->select('products.category_id', DB::raw('AVG(product_details.original_price) as average'), DB::raw('COUNT(*) as count'))
            ->join('products', 'product_details.product_id', '=', 'products.product_id')
            ->where('product_details.is_deleted', 0)
            ->where('products.is_deleted', 0)
            ->where('product_details.original_price', '>', 0)
            ->groupBy('products.category_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->category_id => [
                    'average' => (float) $row->average,
                    'count' => (int) $row->count,
                ],
            ]);
    }

    private function duplicateNameMap(): Collection
    {
        return Product::query()
            ->where('is_deleted', 0)
            ->pluck('name')
            ->map(fn ($name) => $this->normalizeText($name))
            ->filter()
            ->countBy();
    }

    private function normalizeText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::ascii(Str::lower((string) $value))));
    }

    private function validSeverity(string $severity): string
    {
        return in_array($severity, ['critical', 'high', 'medium', 'low', 'info'], true) ? $severity : 'low';
    }

    private function ruleLooksCategoryMismatch(string $name, ?string $categoryName): bool
    {
        if (!$categoryName) {
            return false;
        }

        $nameText = $this->normalizeText($name);
        $categoryText = $this->normalizeText($categoryName);
        $types = ['chair', 'sofa', 'table', 'desk', 'bed', 'wardrobe', 'cabinet', 'lamp', 'rug', 'shelf', 'bookcase'];
        $matches = collect($types)->filter(fn ($type) => Str::contains($nameText, $type));

        return $matches->isNotEmpty() && $matches->every(fn ($type) => !Str::contains($categoryText, $type));
    }
}
