<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductImage;
use App\Models\ProductType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditFurnitureProductCatalog extends Command
{
    protected $signature = 'products:audit-catalog {--json : Print the full JSON report}';

    protected $description = 'Audit product type counts, SKUs, slugs and local product images.';

    public function handle(): int
    {
        $typeCounts = ProductType::withCount(['products' => fn ($query) => $query->where('is_deleted', false)])
            ->orderBy('index')
            ->get()
            ->mapWithKeys(fn (ProductType $type) => [$type->name => $type->products_count])
            ->all();

        $imageIssues = $this->imageIssues();
        $report = [
            'generated_at' => now()->toIso8601String(),
            'type_counts' => $typeCounts,
            'types_below_minimum' => collect($typeCounts)->filter(fn ($count) => $count < 8)->all(),
            'duplicate_product_slugs' => $this->duplicates('products', 'slug'),
            'duplicate_skus' => $this->duplicates('product_details', 'sku'),
            'products_without_sku' => Product::where('is_deleted', false)
                ->whereDoesntHave('detailed_products', fn ($query) => $query->where('is_deleted', false))
                ->pluck('name')
                ->all(),
            'products_without_images' => Product::where('is_deleted', false)
                ->whereDoesntHave('detailed_products.images')
                ->pluck('name')
                ->all(),
            'images_missing_files' => $imageIssues['missing'],
            'images_invalid_files' => $imageIssues['invalid'],
            'svg_images' => $imageIssues['svg'],
            'images_reused_by_multiple_products' => $this->reusedImagesAcrossProducts(),
        ];

        file_put_contents(storage_path('app/product_catalog_audit.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Product type counts');
            foreach ($typeCounts as $type => $count) {
                $this->line('- ' . $type . ': ' . $count);
            }

            $this->line('Duplicate product slugs: ' . count($report['duplicate_product_slugs']));
            $this->line('Duplicate SKUs: ' . count($report['duplicate_skus']));
            $this->line('Products without SKU: ' . count($report['products_without_sku']));
            $this->line('Products without images: ' . count($report['products_without_images']));
            $this->line('Missing image files: ' . count($report['images_missing_files']));
            $this->line('Invalid image files: ' . count($report['images_invalid_files']));
            $this->line('SVG images: ' . count($report['svg_images']));
            $this->line('Images reused by multiple products: ' . count($report['images_reused_by_multiple_products']));
            $this->info('Audit report: ' . storage_path('app/product_catalog_audit.json'));
        }

        $hasCriticalIssues = collect([
            $report['types_below_minimum'],
            $report['duplicate_product_slugs'],
            $report['duplicate_skus'],
            $report['products_without_sku'],
            $report['products_without_images'],
            $report['images_missing_files'],
            $report['images_invalid_files'],
            $report['svg_images'],
            $report['images_reused_by_multiple_products'],
        ])->flatten(1)->isNotEmpty();

        return $hasCriticalIssues ? self::FAILURE : self::SUCCESS;
    }

    private function duplicates(string $table, string $column): array
    {
        return DB::table($table)
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($row) => [
                $column => $row->{$column},
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function imageIssues(): array
    {
        $issues = [
            'missing' => [],
            'invalid' => [],
            'svg' => [],
        ];

        ProductImage::query()->select(['product_image_id', 'sku', 'url'])->orderBy('product_image_id')->each(function (ProductImage $image) use (&$issues) {
            if (Str::endsWith(Str::lower(parse_url($image->url, PHP_URL_PATH) ?: $image->url), '.svg')) {
                $issues['svg'][] = $this->imageIssuePayload($image, 'SVG image is not allowed');

                return;
            }

            $path = $this->localImagePath($image->url);
            if ($path === null || !is_file($path)) {
                $issues['missing'][] = $this->imageIssuePayload($image, 'Local image file does not exist');

                return;
            }

            $info = @getimagesize($path);
            $mime = strtolower((string) ($info['mime'] ?? ''));
            if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ($info[0] ?? 0) < 600 || ($info[1] ?? 0) < 600) {
                $issues['invalid'][] = $this->imageIssuePayload($image, 'Invalid raster image, unsupported MIME or dimensions below 600px');
            }
        });

        return $issues;
    }

    private function imageIssuePayload(ProductImage $image, string $reason): array
    {
        return [
            'product_image_id' => $image->product_image_id,
            'sku' => $image->sku,
            'url' => $image->url,
            'reason' => $reason,
        ];
    }

    private function localImagePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'images/products/')) {
            return public_path($path);
        }

        if (Str::startsWith($path, 'storage/')) {
            return public_path($path);
        }

        return null;
    }

    private function reusedImagesAcrossProducts(): array
    {
        return ProductImage::query()
            ->join('product_details', 'product_images.sku', '=', 'product_details.sku')
            ->select('product_images.url', DB::raw('COUNT(DISTINCT product_details.product_id) as product_count'))
            ->groupBy('product_images.url')
            ->havingRaw('COUNT(DISTINCT product_details.product_id) > 1')
            ->get()
            ->map(fn ($row) => [
                'url' => $row->url,
                'product_count' => (int) $row->product_count,
            ])
            ->all();
    }
}
