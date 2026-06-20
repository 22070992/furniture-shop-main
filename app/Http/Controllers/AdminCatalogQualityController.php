<?php

namespace App\Http\Controllers;

use App\Models\CatalogQualityIssue;
use App\Models\CatalogQualityRun;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogQualityChecker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCatalogQualityController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.catalog_quality.index', $this->indexData($request));
    }

    public function show(Request $request, CatalogQualityRun $run)
    {
        $data = $this->indexData($request, $run);
        $data['run'] = $run->load('starter');

        return view('admin.catalog_quality.show', $data);
    }

    public function store(Request $request, CatalogQualityChecker $checker)
    {
        $data = $request->validate([
            'scan_type' => ['required', Rule::in([CatalogQualityRun::SCAN_RULE, CatalogQualityRun::SCAN_AI])],
            'scope' => ['required', Rule::in(['all', 'category', 'product'])],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,category_id'],
            'product_id' => ['nullable', 'integer', 'exists:products,product_id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('shop.catalog_quality.max_scan_products', 100)],
        ]);

        if ($data['scope'] === 'category') {
            $request->validate(['category_id' => ['required', 'integer', 'exists:categories,category_id']]);
        }

        if ($data['scope'] === 'product') {
            $request->validate(['product_id' => ['required', 'integer', 'exists:products,product_id']]);
        }

        $run = $checker->run($request->user(), $data);

        return redirect()
            ->route('admin.catalog-quality.runs.show', $run)
            ->with('success', 'Catalog quality scan completed. Found ' . $run->total_issues . ' issues.');
    }

    public function updateIssue(Request $request, CatalogQualityIssue $issue, CatalogQualityChecker $checker)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                CatalogQualityIssue::STATUS_OPEN,
                CatalogQualityIssue::STATUS_IGNORED,
                CatalogQualityIssue::STATUS_RESOLVED,
            ])],
        ]);

        $checker->updateIssueStatus($issue, $data['status']);

        return back()->with('success', 'Issue status updated.');
    }

    public function applySuggestion(CatalogQualityIssue $issue, CatalogQualityChecker $checker)
    {
        $checker->applySuggestion($issue);

        return back()->with('success', 'Suggestion applied and issue marked resolved.');
    }

    public function rerunProduct(Request $request, Product $product, CatalogQualityChecker $checker)
    {
        $data = $request->validate([
            'scan_type' => ['required', Rule::in([CatalogQualityRun::SCAN_RULE, CatalogQualityRun::SCAN_AI])],
        ]);

        $run = $checker->rerunProduct($request->user(), $product, $data['scan_type']);

        return redirect()
            ->route('admin.catalog-quality.runs.show', $run)
            ->with('success', 'Product scan completed.');
    }

    private function indexData(Request $request, ?CatalogQualityRun $run = null): array
    {
        $query = CatalogQualityIssue::with(['run.starter', 'product.category', 'productDetail.color'])
            ->latest('catalog_quality_issues.created_at');

        if ($run) {
            $query->where('run_id', $run->id);
        }

        $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('issue_code'), fn ($q) => $q->where('issue_code', $request->string('issue_code')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', (int) $request->product_id))
            ->when($request->filled('sku'), fn ($q) => $q->where('sku', 'like', '%' . $request->sku . '%'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = '%' . $request->q . '%';
                $q->where(function ($nested) use ($search) {
                    $nested->where('issue_code', 'like', $search)
                        ->orWhere('current_value', 'like', $search)
                        ->orWhere('suggestion', 'like', $search)
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', $search));
                });
            });

        $stats = [
            'runs' => CatalogQualityRun::count(),
            'issues' => CatalogQualityIssue::count(),
            'open' => CatalogQualityIssue::where('status', CatalogQualityIssue::STATUS_OPEN)->count(),
            'critical' => CatalogQualityIssue::where('severity', 'critical')->where('status', CatalogQualityIssue::STATUS_OPEN)->count(),
            'resolved' => CatalogQualityIssue::where('status', CatalogQualityIssue::STATUS_RESOLVED)->count(),
        ];

        return [
            'issues' => $query->paginate(15)->withQueryString(),
            'runs' => CatalogQualityRun::with('starter')->latest('started_at')->limit(8)->get(),
            'stats' => $stats,
            'categories' => Category::orderBy('name')->get(['category_id', 'name']),
            'products' => Product::orderBy('name')->limit(250)->get(['product_id', 'name']),
            'issueCodes' => CatalogQualityIssue::select('issue_code')->distinct()->orderBy('issue_code')->pluck('issue_code'),
            'statuses' => [
                CatalogQualityIssue::STATUS_OPEN,
                CatalogQualityIssue::STATUS_IGNORED,
                CatalogQualityIssue::STATUS_RESOLVED,
            ],
            'severities' => ['critical', 'high', 'medium', 'low', 'info'],
            'sources' => [
                CatalogQualityIssue::SOURCE_RULE,
                CatalogQualityIssue::SOURCE_AI,
            ],
        ];
    }
}
