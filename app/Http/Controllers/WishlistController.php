<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use App\Services\ProductCatalogPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function index(ProductCatalogPresenter $presenter)
    {
        if (!Auth::check()) {
            session()->put('url.intended', route('wishlist.index'));

            return redirect()->route('login');
        }

        $items = WishlistItem::where('user_id', Auth::id())
            ->latest()
            ->get();

        $products = $presenter->getActiveProductsByIds($items->pluck('product_id')->all());

        return view('pages.wishlist.index', [
            'page' => 'Wishlist',
            'products' => $products,
        ]);
    }

    public function store(Request $request, ProductCatalogPresenter $presenter)
    {
        if (!Auth::check()) {
            session()->put('url.intended', url()->previous());

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please sign in to save products to your wishlist.',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = $presenter->findActiveProduct((int) $data['product_id']);

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => 'Product is not available.',
            ]);
        }

        WishlistItem::firstOrCreate([
            'user_id' => Auth::id(),
            'product_id' => $product->product_id,
        ]);

        return $this->respond($request, true, 'Product added to wishlist.');
    }

    public function destroy(Request $request, int $productId)
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Please sign in to update your wishlist.',
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()->route('login');
        }

        WishlistItem::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->delete();

        return $this->respond($request, false, 'Product removed from wishlist.');
    }

    private function respond(Request $request, bool $wishlisted, string $message)
    {
        $count = WishlistItem::where('user_id', Auth::id())->count();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'wishlisted' => $wishlisted,
                'count' => $count,
            ]);
        }

        return back()->with('success', $message);
    }
}
