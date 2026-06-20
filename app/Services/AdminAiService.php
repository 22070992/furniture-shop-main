<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ReceivingReport;
use App\Models\SiteVisit;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminAiService
{
    public function generateProductContent(array $input): array
    {
        $title = trim($input['title'] ?? '');
        $category = Category::find($input['category_id'] ?? null);
        $brand = Brand::find($input['brand_id'] ?? null);
        $categoryName = $category?->name ?? 'Furniture';
        $brandName = $brand?->name ?? 'Furemir';
        $productName = $title !== '' ? $title : $categoryName . ' by ' . $brandName;
        $profile = $this->productProfile($productName, $categoryName);
        $material = $input['material'] ?? $profile['material'];
        $shortDescription = $this->productShortDescription($productName, $categoryName, $material, $profile);
        $description = $this->productDetailedDescription($productName, $categoryName, $material, $brandName, $profile);
        $suggestedTags = $this->suggestTags($productName, $categoryName, $material, $profile['keywords']);
        $seoDescription = Str::limit(strip_tags($shortDescription . ' ' . $profile['seo']), 155, '');

        return [
            'name' => $productName,
            'short_description' => $shortDescription,
            'material' => $material,
            'description' => $description,
            'suggested_tags' => $suggestedTags,
            'seo' => [
                'title' => Str::limit($productName . ' | Furemir Furniture', 60, ''),
                'description' => $seoDescription,
                'keywords' => array_values(array_unique(array_merge(
                    [$productName, $categoryName, $brandName, $material],
                    $profile['keywords']
                ))),
            ],
        ];
    }

    public function dashboardSummary(): array
    {
        $now = now();
        $last7Start = $now->copy()->subDays(7);
        $previous7Start = $now->copy()->subDays(14);
        $previous7End = $now->copy()->subDays(7);

        $orders7 = Order::where('created_at', '>=', $last7Start)->count();
        $ordersPrevious7 = Order::whereBetween('created_at', [$previous7Start, $previous7End])->count();
        $revenue7 = (float) Order::where('is_paid', true)->where('created_at', '>=', $last7Start)->sum('total_price');
        $revenuePrevious7 = (float) Order::where('is_paid', true)->whereBetween('created_at', [$previous7Start, $previous7End])->sum('total_price');
        $waitingPayments = Order::where('is_paid', false)->where('created_at', '>=', $last7Start)->count();
        $pendingOrders = Order::where('status', 0)->where('created_at', '>=', $last7Start)->count();
        $shippedOrders = Order::where('status', 3)->where('created_at', '>=', $last7Start)->count();
        $canceledOrders = Order::where('status', 4)->where('created_at', '>=', $last7Start)->count();
        $newUsers = User::where('is_active', true)->where('is_staff', false)->where('created_at', '>=', $last7Start)->count();
        $receipts30 = ReceivingReport::where('created_at', '>=', $now->copy()->subDays(30))->count();
        $hasTrafficData = Schema::hasTable('site_visits');
        $visits7 = $hasTrafficData ? SiteVisit::where('created_at', '>=', $last7Start)->count() : 0;
        $visitsPrevious7 = $hasTrafficData ? SiteVisit::whereBetween('created_at', [$previous7Start, $previous7End])->count() : 0;
        $uniqueSessions7 = $hasTrafficData ? SiteVisit::where('created_at', '>=', $last7Start)->distinct('session_id')->count('session_id') : 0;
        $visitTrend = $this->percentageChange($visits7, $visitsPrevious7);
        $conversionRate = $uniqueSessions7 > 0 ? round(($orders7 / $uniqueSessions7) * 100, 1) : 0;
        $topTrafficSource = $hasTrafficData
            ? SiteVisit::select('source', DB::raw('COUNT(*) as visits'))
                ->where('created_at', '>=', $last7Start)
                ->groupBy('source')
                ->orderByDesc('visits')
                ->first()
            : null;
        $lowStock = ProductDetail::with('product')
            ->where('is_deleted', 0)
            ->where('quantities', '<=', 5)
            ->orderBy('quantities')
            ->limit(5)
            ->get();
        $outOfStockCount = ProductDetail::where('is_deleted', 0)->where('quantities', 0)->count();
        $topProducts = $this->topProducts($last7Start);
        $revenueTrend = $this->percentageChange($revenue7, $revenuePrevious7);
        $orderTrend = $this->percentageChange($orders7, $ordersPrevious7);

        return [
            'headline' => $this->dashboardHeadline($revenueTrend, $orderTrend, $waitingPayments, $lowStock->count()),
            'overview' => 'AI đang đọc dữ liệu 7 ngày gần nhất để tóm tắt lượt truy cập, doanh thu, đơn hàng, khách mới và tồn kho cần xử lý.',
            'metrics' => [
                [
                    'label' => 'Visits 7 days',
                    'value' => (string) $visits7,
                    'status' => $hasTrafficData ? $this->formatTrend($visitTrend) : 'No traffic table',
                    'tone' => $visitTrend >= 0 ? 'success' : 'danger',
                ],
                [
                    'label' => 'Conversion rate',
                    'value' => $conversionRate . '%',
                    'status' => $uniqueSessions7 . ' unique sessions',
                    'tone' => $conversionRate >= 2 ? 'success' : 'warning',
                ],
                [
                    'label' => 'Revenue 7 days',
                    'value' => number_format($revenue7, 0, '.', ',') . 'đ',
                    'status' => $this->formatTrend($revenueTrend),
                    'tone' => $revenueTrend >= 0 ? 'success' : 'danger',
                ],
                [
                    'label' => 'Orders 7 days',
                    'value' => (string) $orders7,
                    'status' => $this->formatTrend($orderTrend),
                    'tone' => $orderTrend >= 0 ? 'success' : 'danger',
                ],
                [
                    'label' => 'Waiting payment',
                    'value' => (string) $waitingPayments,
                    'status' => $waitingPayments > 0 ? 'Need follow-up' : 'Clear',
                    'tone' => $waitingPayments > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Low stock SKUs',
                    'value' => (string) $lowStock->count(),
                    'status' => $outOfStockCount . ' out of stock',
                    'tone' => $lowStock->count() > 0 ? 'warning' : 'success',
                ],
            ],
            'insights' => $this->dashboardInsights(
                $orders7,
                $revenue7,
                $waitingPayments,
                $pendingOrders,
                $shippedOrders,
                $canceledOrders,
                $newUsers,
                $receipts30,
                $visits7,
                $conversionRate,
                $topTrafficSource,
                $topProducts,
                $lowStock
            ),
            'actions' => $this->dashboardActions($waitingPayments, $pendingOrders, $lowStock, $topProducts, $conversionRate, $topTrafficSource),
            'top_products' => $topProducts,
            'low_stock' => $lowStock->map(fn ($detail) => [
                'sku' => $detail->sku,
                'name' => $detail->product?->name ?? $detail->name,
                'quantities' => $detail->quantities,
            ])->values(),
        ];
    }

    public function catalogQualityTextReview(Product $product): array
    {
        $categoryName = $product->category?->name ?? 'Furniture';
        $description = trim(strip_tags((string) $product->description));
        $shortDescription = trim((string) $product->short_description);
        $generated = $this->generateProductContent([
            'title' => $product->name,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'material' => $product->material,
        ]);
        $issues = [];

        if (Str::length($product->name) < 8 || preg_match('/^(product|furniture|chair|table|sofa)$/i', trim($product->name))) {
            $issues[] = [
                'issue_code' => 'AI_WEAK_PRODUCT_NAME',
                'severity' => 'medium',
                'field_name' => 'name',
                'current_value' => $product->name,
                'suggestion' => Str::limit($generated['name'], 120, ''),
                'metadata' => ['reason' => 'Product name is too generic for catalog browsing and SEO.'],
            ];
        }

        if ($shortDescription === '' || Str::length($shortDescription) < 45) {
            $issues[] = [
                'issue_code' => 'AI_SHORT_DESCRIPTION_SUGGESTION',
                'severity' => 'low',
                'field_name' => 'short_description',
                'current_value' => $shortDescription,
                'suggestion' => $generated['short_description'],
                'metadata' => ['reason' => 'Short description can better summarize material, category and benefit.'],
            ];
        }

        if ($description !== '' && $this->hasRepeatedText($description)) {
            $issues[] = [
                'issue_code' => 'AI_REPETITIVE_DESCRIPTION',
                'severity' => 'medium',
                'field_name' => 'description',
                'current_value' => Str::limit($description, 500),
                'suggestion' => $generated['description'],
                'metadata' => ['reason' => 'Description appears to repeat the same wording multiple times.'],
            ];
        }

        if ($description === '' || Str::length($description) < 120) {
            $issues[] = [
                'issue_code' => 'AI_DESCRIPTION_IMPROVEMENT',
                'severity' => 'medium',
                'field_name' => 'description',
                'current_value' => Str::limit($description, 500),
                'suggestion' => $generated['description'],
                'metadata' => ['reason' => 'Description should explain use case, material and benefits.'],
            ];
        }

        if ($product->material === null || trim((string) $product->material) === '') {
            $issues[] = [
                'issue_code' => 'AI_MATERIAL_SUGGESTION',
                'severity' => 'low',
                'field_name' => 'material',
                'current_value' => '',
                'suggestion' => $generated['material'],
                'metadata' => ['reason' => 'Material is important for furniture comparison and filtering.'],
            ];
        }

        if ($this->looksCategoryMismatch($product->name, $categoryName)) {
            $issues[] = [
                'issue_code' => 'AI_POSSIBLE_CATEGORY_MISMATCH',
                'severity' => 'high',
                'field_name' => 'category_id',
                'current_value' => $categoryName,
                'suggestion' => 'Review category assignment for "' . $product->name . '".',
                'metadata' => [
                    'product_name' => $product->name,
                    'category_name' => $categoryName,
                    'reason' => 'Name contains furniture type words that do not align with the assigned category.',
                ],
            ];
        }

        return $issues;
    }

    public function interiorComboRecommendation(array $input, array $candidates, array $settings): array|string
    {
        $maxProducts = min(5, max(3, (int) ($settings['max_products'] ?? 5)));
        $budget = isset($input['budget']) ? (float) $input['budget'] : null;
        $room = Str::lower((string) ($input['room'] ?? 'room'));
        $style = Str::lower((string) ($input['style'] ?? ''));
        $selected = [];
        $usedCategories = [];
        $runningTotal = 0;

        usort($candidates, function ($left, $right) {
            return ($right['rating'] <=> $left['rating'])
                ?: ($right['review_count'] <=> $left['review_count'])
                ?: ($left['min_price'] <=> $right['min_price']);
        });

        foreach ($candidates as $candidate) {
            if (count($selected) >= $maxProducts) {
                break;
            }

            $skus = collect($candidate['skus'] ?? [])->filter(fn ($sku) => ($sku['stock'] ?? 0) > 0)->values();
            if ($skus->isEmpty()) {
                continue;
            }

            $category = Str::lower((string) ($candidate['category'] ?? 'furniture'));
            if (isset($usedCategories[$category]) && count($selected) < 3) {
                continue;
            }

            $sku = $skus->sortBy('price')->first();
            $price = (float) ($sku['price'] ?? $candidate['min_price'] ?? 0);
            if ($budget && count($selected) >= 3 && ($runningTotal + $price) > $budget * 1.1) {
                continue;
            }

            $selected[] = [
                'product_id' => (int) $candidate['product_id'],
                'sku' => $sku['sku'],
                'role' => $this->interiorComboRole((string) ($candidate['category'] ?? 'Furniture')),
                'reason' => $this->interiorComboReason((string) ($candidate['name'] ?? 'This product'), (string) ($candidate['category'] ?? 'furniture'), $room, $style),
            ];
            $usedCategories[$category] = true;
            $runningTotal += $price;
        }

        return [
            'combo' => $selected,
            'notes' => [
                'Furemir AI only selected product IDs and SKUs from the backend candidate list.',
                'Prices and stock are verified by backend after this suggestion.',
            ],
        ];
    }

    private function productProfile(string $title, string $categoryName): array
    {
        $text = Str::ascii(Str::lower($title . ' ' . $categoryName));

        if (Str::contains($text, ['office', 'van phong', 'task', 'ergonomic'])) {
            return [
                'material' => 'Breathable mesh, molded foam, steel and nylon frame',
                'keywords' => ['ergonomic chair', 'office chair', 'lumbar support', 'work from home'],
                'benefits' => ['supportive seating for long work sessions', 'smooth height adjustment', 'stable rolling base'],
                'seo' => 'Ergonomic office chair for productive home office and studio workspaces.',
            ];
        }

        if (Str::contains($text, ['hanging', 'treo', 'swing', 'cocoon', 'egg'])) {
            return [
                'material' => 'Handwoven rattan, powder-coated steel frame and soft cushion',
                'keywords' => ['hanging chair', 'rattan chair', 'relax chair', 'patio chair'],
                'benefits' => ['relaxed lounge feel', 'textured rattan look', 'comfortable cushion support'],
                'seo' => 'Hanging chair for balcony, patio and cozy reading corners.',
            ];
        }

        if (Str::contains($text, ['folding', 'gap', 'xep', 'camp', 'bistro'])) {
            return [
                'material' => 'Solid wood or powder-coated metal with reinforced folding joints',
                'keywords' => ['folding chair', 'compact chair', 'space saving', 'portable chair'],
                'benefits' => ['easy storage', 'portable daily use', 'stable foldable structure'],
                'seo' => 'Compact folding chair for flexible dining, balcony and extra seating.',
            ];
        }

        if (Str::contains($text, ['table', 'desk'])) {
            return [
                'material' => 'Engineered wood, oak veneer and reinforced metal hardware',
                'keywords' => ['furniture table', 'modern table', 'wood furniture', 'living room'],
                'benefits' => ['clean modern profile', 'durable daily surface', 'easy room pairing'],
                'seo' => 'Modern furniture table for warm and practical interiors.',
            ];
        }

        return [
            'material' => 'Premium wood, fabric and durable metal hardware',
            'keywords' => ['modern furniture', 'home furniture', 'interior design', 'Furemir'],
            'benefits' => ['balanced proportions', 'durable everyday construction', 'easy styling with modern rooms'],
            'seo' => 'Modern home furniture designed for everyday comfort and refined interiors.',
        ];
    }

    private function productShortDescription(string $name, string $categoryName, string $material, array $profile): string
    {
        $category = Str::lower($categoryName);

        return $name . ' is ' . $this->articleFor($category) . ' ' . $category . ' crafted from ' . $material . ', designed for ' . $profile['benefits'][0] . '.';
    }

    private function productDetailedDescription(string $name, string $categoryName, string $material, string $brandName, array $profile): string
    {
        return '<p><strong>' . e($name) . '</strong> brings Furemir comfort into a modern interior with a practical '
            . e(Str::lower($categoryName)) . ' design. The product uses ' . e($material)
            . ' so it feels reliable in daily use while still looking refined.</p>'
            . '<ul>'
            . '<li>' . e(Str::ucfirst($profile['benefits'][0])) . '.</li>'
            . '<li>' . e(Str::ucfirst($profile['benefits'][1])) . '.</li>'
            . '<li>' . e(Str::ucfirst($profile['benefits'][2])) . '.</li>'
            . '<li>Pairs well with the ' . e($brandName) . ' furniture collection.</li>'
            . '</ul>';
    }

    private function suggestTags(string $title, string $categoryName, string $material, array $keywords)
    {
        $haystack = Str::ascii(Str::lower($title . ' ' . $categoryName . ' ' . $material . ' ' . implode(' ', $keywords)));
        $categoryTag = Str::ascii(Str::lower($categoryName));

        return Tag::all()
            ->filter(function ($tag) use ($haystack, $categoryTag) {
                $tagName = Str::ascii(Str::lower($tag->name));
                $tagSingular = Str::singular($tagName);

                if ($tagName === $categoryTag || Str::contains($haystack, $tagName) || Str::contains($haystack, $tagSingular)) {
                    return true;
                }

                if ($tagName === 'modern') {
                    return true;
                }

                if ($tagName === 'work-from-home' && Str::contains($haystack, ['office', 'task', 'desk', 'work'])) {
                    return true;
                }

                if ($tagName === 'compact' && Str::contains($haystack, ['compact', 'folding', 'space saving', 'portable'])) {
                    return true;
                }

                if ($tagName === 'natural' && Str::contains($haystack, ['wood', 'oak', 'rattan', 'natural'])) {
                    return true;
                }

                if ($tagName === 'outdoor' && Str::contains($haystack, ['outdoor', 'patio', 'hanging', 'rattan'])) {
                    return true;
                }

                return false;
            })
            ->take(5)
            ->map(fn ($tag) => ['id' => $tag->tag_id, 'name' => $tag->name])
            ->values();
    }

    private function hasRepeatedText(string $description): bool
    {
        $sentences = collect(preg_split('/[.!?]+/', Str::lower($description)))
            ->map(fn ($sentence) => trim(preg_replace('/\s+/', ' ', $sentence)))
            ->filter(fn ($sentence) => Str::length($sentence) > 25);

        return $sentences->count() > $sentences->unique()->count();
    }

    private function looksCategoryMismatch(string $name, string $categoryName): bool
    {
        $nameText = Str::ascii(Str::lower($name));
        $categoryText = Str::ascii(Str::lower($categoryName));
        $knownTypes = [
            'chair',
            'sofa',
            'table',
            'desk',
            'bed',
            'wardrobe',
            'cabinet',
            'lamp',
            'rug',
            'shelf',
            'bookcase',
        ];

        $nameTypes = collect($knownTypes)->filter(fn ($type) => Str::contains($nameText, $type));

        if ($nameTypes->isEmpty()) {
            return false;
        }

        return $nameTypes->every(fn ($type) => !Str::contains($categoryText, $type));
    }

    private function articleFor(string $phrase): string
    {
        return Str::startsWith(Str::ascii(Str::lower($phrase)), ['a', 'e', 'i', 'o', 'u']) ? 'an' : 'a';
    }

    private function interiorComboRole(string $category): string
    {
        $categoryText = Str::lower($category);

        return match (true) {
            Str::contains($categoryText, ['sofa', 'chair', 'bed']) => 'Main comfort piece',
            Str::contains($categoryText, ['desk', 'table']) => 'Functional surface',
            Str::contains($categoryText, ['lamp', 'light']) => 'Lighting layer',
            Str::contains($categoryText, ['rug']) => 'Texture and zoning',
            Str::contains($categoryText, ['cabinet', 'wardrobe', 'shelf', 'bookcase']) => 'Storage and organization',
            default => 'Room accent',
        };
    }

    private function interiorComboReason(string $name, string $category, string $room, string $style): string
    {
        $reason = $name . ' gives the ' . ($room ?: 'room') . ' a useful ' . Str::lower($category) . ' layer';

        if ($style !== '') {
            $reason .= ' while staying close to the ' . $style . ' style brief';
        }

        return $reason . '.';
    }

    private function topProducts($startDate)
    {
        return OrderDetail::select([
            'products.product_id',
            'products.name',
            DB::raw('SUM(order_details.quantities) as sold'),
            DB::raw('SUM(order_details.quantities * order_details.unit_price) as revenue'),
        ])
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->join('product_details', 'order_details.sku', '=', 'product_details.sku')
            ->join('products', 'product_details.product_id', '=', 'products.product_id')
            ->where('orders.created_at', '>=', $startDate)
            ->groupBy('products.product_id', 'products.name')
            ->orderByDesc('sold')
            ->limit(3)
            ->get()
            ->map(fn ($product) => [
                'id' => $product->product_id,
                'name' => $product->name,
                'sold' => (int) $product->sold,
                'revenue' => (float) $product->revenue,
            ])
            ->values();
    }

    private function dashboardHeadline(float $revenueTrend, float $orderTrend, int $waitingPayments, int $lowStockCount): string
    {
        if ($waitingPayments > 0) {
            return 'Ưu tiên hôm nay: theo dõi thanh toán đang chờ để tránh thất thoát đơn hàng.';
        }

        if ($lowStockCount > 0) {
            return 'Một số SKU sắp hết hàng, nên kiểm tra nhập kho trước khi chạy khuyến mãi.';
        }

        if ($revenueTrend >= 0 && $orderTrend >= 0) {
            return 'Hiệu suất tuần này đang tích cực, có thể đẩy thêm sản phẩm bán chạy.';
        }

        return 'Dashboard đang có tín hiệu giảm, nên rà lại đơn hàng và nhóm sản phẩm chủ lực.';
    }

    private function dashboardInsights(
        int $orders7,
        float $revenue7,
        int $waitingPayments,
        int $pendingOrders,
        int $shippedOrders,
        int $canceledOrders,
        int $newUsers,
        int $receipts30,
        int $visits7,
        float $conversionRate,
        $topTrafficSource,
        $topProducts,
        $lowStock
    ): array {
        $insights = [
            '7 ngày gần nhất có ' . $visits7 . ' lượt truy cập với conversion rate khoảng ' . $conversionRate . '%.',
            '7 ngày gần nhất có ' . $orders7 . ' đơn với doanh thu đã thanh toán ' . number_format($revenue7, 0, '.', ',') . 'đ.',
            $newUsers . ' khách hàng mới được ghi nhận, đây là nhóm nên chăm sóc bằng voucher hoặc sản phẩm bán chạy.',
        ];

        if ($topTrafficSource) {
            $insights[] = 'Nguồn truy cập mạnh nhất hiện tại là ' . ($topTrafficSource->source ?: 'direct') . ' với ' . $topTrafficSource->visits . ' lượt.';
        }

        if ($waitingPayments > 0) {
            $insights[] = $waitingPayments . ' đơn đang chờ thanh toán, admin nên liên hệ hoặc kiểm tra trạng thái cổng thanh toán.';
        }

        if ($pendingOrders > 0) {
            $insights[] = $pendingOrders . ' đơn chưa xác nhận, cần xử lý trước các đơn mới hơn để giảm thời gian chờ.';
        }

        if ($shippedOrders > 0 || $canceledOrders > 0) {
            $insights[] = 'Đã giao ' . $shippedOrders . ' đơn và hủy ' . $canceledOrders . ' đơn trong 7 ngày gần nhất.';
        }

        if ($topProducts->isNotEmpty()) {
            $insights[] = 'Sản phẩm nổi bật: ' . $topProducts->first()['name'] . ' với ' . $topProducts->first()['sold'] . ' lượt bán.';
        }

        if ($lowStock->isNotEmpty()) {
            $insights[] = $lowStock->count() . ' SKU còn từ 5 sản phẩm trở xuống, cần nhập thêm trước khi quảng bá.';
        }

        if ($receipts30 === 0) {
            $insights[] = '30 ngày gần nhất chưa có phiếu nhập mới, nên kiểm tra lại kế hoạch nhập kho.';
        }

        return $insights;
    }

    private function dashboardActions(int $waitingPayments, int $pendingOrders, $lowStock, $topProducts, float $conversionRate, $topTrafficSource): array
    {
        $actions = [];

        if ($conversionRate > 0 && $conversionRate < 2) {
            $actions[] = 'Kiểm tra trang sản phẩm/checkout vì conversion rate đang thấp hơn 2%.';
        }

        if ($topTrafficSource && in_array($topTrafficSource->source, ['facebook', 'instagram', 'tiktok'], true)) {
            $actions[] = 'Tạo landing/filter theo sản phẩm bán chạy cho nguồn ' . $topTrafficSource->source . '.';
        }

        if ($pendingOrders > 0) {
            $actions[] = 'Xử lý các đơn Awaiting Confirm trước khi kiểm tra báo cáo doanh thu.';
        }

        if ($waitingPayments > 0) {
            $actions[] = 'Lọc danh sách đơn chưa thanh toán và nhắc khách hoàn tất thanh toán.';
        }

        if ($lowStock->isNotEmpty()) {
            $actions[] = 'Tạo phiếu nhập cho các SKU tồn kho thấp, ưu tiên SKU còn 0 sản phẩm.';
        }

        if ($topProducts->isNotEmpty()) {
            $actions[] = 'Đưa sản phẩm bán chạy lên banner hoặc chương trình khuyến mãi nhẹ.';
        }

        if (empty($actions)) {
            $actions[] = 'Tiếp tục theo dõi doanh thu và chuẩn bị chiến dịch cho nhóm sản phẩm có rating cao.';
        }

        return $actions;
    }

    private function percentageChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function formatTrend(float $trend): string
    {
        if ($trend === 0.0) {
            return 'No change';
        }

        return ($trend > 0 ? '+' : '') . $trend . '% vs previous 7 days';
    }
}
