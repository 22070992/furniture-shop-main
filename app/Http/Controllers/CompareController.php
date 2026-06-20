<?php

namespace App\Http\Controllers;

use App\Services\ProductCatalogPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompareController extends Controller
{
    private const SESSION_KEY = 'compare_product_ids';
    private const MAX_PRODUCTS = 4;

    public function index(Request $request, ProductCatalogPresenter $presenter)
    {
        $ids = $this->ids($request);
        $products = $presenter->getActiveProductsByIds($ids);
        $validIds = $products->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all();

        $request->session()->put(self::SESSION_KEY, $validIds);

        return view('pages.compare.index', [
            'page' => 'Compare',
            'products' => $products,
            'compareCount' => count($validIds),
        ]);
    }

    public function store(Request $request, ProductCatalogPresenter $presenter)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = $presenter->findActiveProduct((int) $data['product_id']);

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => 'Product is not available for comparison.',
            ]);
        }

        $ids = $this->ids($request);
        $productId = (int) $product->product_id;

        if (in_array($productId, $ids, true)) {
            return $this->respond($request, 'Product is already in compare.', $ids);
        }

        if (count($ids) >= self::MAX_PRODUCTS) {
            throw ValidationException::withMessages([
                'product_id' => 'You can compare up to 4 products.',
            ]);
        }

        $ids[] = $productId;
        $request->session()->put(self::SESSION_KEY, $ids);

        return $this->respond($request, 'Product added to compare.', $ids);
    }

    public function destroy(Request $request, int $productId)
    {
        $ids = collect($this->ids($request))
            ->reject(fn ($id) => (int) $id === (int) $productId)
            ->values()
            ->all();

        $request->session()->put(self::SESSION_KEY, $ids);

        return $this->respond($request, 'Product removed from compare.', $ids);
    }

    public function clear(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return $this->respond($request, 'Compare list cleared.', []);
    }

    public function status(Request $request)
    {
        return response()->json([
            'ids' => $this->ids($request),
            'count' => count($this->ids($request)),
            'max' => self::MAX_PRODUCTS,
        ]);
    }

    private function ids(Request $request): array
    {
        return collect($request->session()->get(self::SESSION_KEY, []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(self::MAX_PRODUCTS)
            ->values()
            ->all();
    }

    private function respond(Request $request, string $message, array $ids)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'ids' => array_values($ids),
                'count' => count($ids),
                'max' => self::MAX_PRODUCTS,
            ]);
        }

        return back()->with('success', $message);
    }
}
