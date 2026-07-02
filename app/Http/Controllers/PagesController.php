<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployee;
use App\Models\Address;
use App\Models\Category;
use App\Models\Color;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ProductType;
use App\Models\Review;
use App\Models\Tag;
use App\Services\ProductRecommendationService;
use App\Services\SeoService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PagesController extends Controller
{
    //

    public function getDealProducts()
    {
        $deal_products = Product::select([
            'products.product_id',
            'products.name',
            'product_details.sku',
            'product_images.url',
            'percentage',
            DB::raw('round(product_details.original_price,0) AS old_price'),
            DB::raw('round(product_details.original_price * (1 - discounts.percentage/100),0) AS new_price'),
            DB::raw('round(discounts.percentage,0) AS discount_percent'),
        ])
            ->distinct()
            ->join('product_details', 'products.product_id', '=', 'product_details.product_id')
            ->leftjoin('product_images', 'product_details.sku', '=', 'product_images.sku')
            ->join('product_discounts', 'product_details.sku', '=', 'product_discounts.sku')
            ->join('discounts', 'product_discounts.discount_id', '=', 'discounts.discount_id')
            ->where(function ($query) {
                $query->where('discounts.is_active', '!=', 0)
                    ->whereDate('discounts.start_date', '<=', now())
                    ->whereDate('discounts.end_date', '>=', now());
            })
            ->where('products.is_deleted', false)
            ->orderBy('discounts.percentage', 'desc')
            ->get();



        return $deal_products;
    }
    public function getLatestProducts()
    {
        // latest product
        $query = Product::with(
            [
                'detailed_products' => function ($query) {
                    $query->where('is_deleted', 0)->with('images', 'color', 'product_discounts.discount');
                },
            ]
        )->where('is_deleted', false)->has('detailed_products');


        $latest_products = $query->orderByDesc('created_at')->paginate(8);
        foreach ($latest_products as $product) {
            $total_discount_percentage = 0;
            foreach ($product->detailed_products as $detailed_product) {
                foreach ($detailed_product->product_discounts as $product_discount) {
                    if ($product_discount->discount->is_currently_active()) {
                        $total_discount_percentage += $product_discount->discount->percentage;
                    }
                }
                $detailed_product->total_discount_percentage = $total_discount_percentage;
            }
        }

        $today = now();
        foreach ($latest_products as $product) {
            $detailed_product =
                $product->detailed_products
                ->sortByDesc(function ($detailed_product) use ($today) {
                    return $detailed_product->product_discounts
                        ->where('discount.start_date', '<=', $today)
                        ->where('discount.end_date', '>=', $today)
                        ->sum('discount.percentage');
                })
                ->first() ?? $product->detailed_products->first();

            if (isset($detailed_product->images->first()->url)) {
                $detailed_product->image = $detailed_product->images->first()->url;
                $detailed_product->color_name = $detailed_product->color?->name;
                $detailed_product->color_code = $detailed_product->color?->code;
                $detailed_product->setRelation('images', null);
            }
            $product->detailed_product = $detailed_product;
            $product->price = $detailed_product->original_price;
            $total_quantities = $product->detailed_products->sum('quantities');
            $product->setRelation('detailed_products', null);
            $product->total_quantities = $total_quantities;
        }
        return $latest_products;
    }

    public function getBestSellerProducts()
    {
        // best seller
        $query = Product::with(
            [
                'detailed_products' => function ($query) {
                    $query->where('is_deleted', 0)->with('images', 'color', 'product_discounts.discount');
                },
            ]
        )->where('is_deleted', false)->has('detailed_products');


        $best_seller_products = $query->orderByDesc('amount_sold')->paginate(8);
        foreach ($best_seller_products as $product) {
            $total_discount_percentage = 0;
            foreach ($product->detailed_products as $detailed_product) {
                foreach ($detailed_product->product_discounts as $product_discount) {
                    if ($product_discount->discount->is_currently_active()) {
                        $total_discount_percentage += $product_discount->discount->percentage;
                    }
                }
                $detailed_product->total_discount_percentage = $total_discount_percentage;
            }
        }

        $today = now();
        foreach ($best_seller_products as $product) {
            $detailed_product =
                $product->detailed_products
                ->sortByDesc(function ($detailed_product) use ($today) {
                    return $detailed_product->product_discounts
                        ->where('discount.start_date', '<=', $today)
                        ->where('discount.end_date', '>=', $today)
                        ->sum('discount.percentage');
                })
                ->first() ?? $product->detailed_products->first();

            if (isset($detailed_product->images->first()->url)) {
                $detailed_product->image = $detailed_product->images->first()->url;
                $detailed_product->color_name = $detailed_product->color?->name;
                $detailed_product->color_code = $detailed_product->color?->code;
                $detailed_product->setRelation('images', null);
            }
            $product->detailed_product = $detailed_product;
            $product->price = $detailed_product->original_price;
            $total_quantities = $product->detailed_products->sum('quantities');
            $product->setRelation('detailed_products', null);
            $product->total_quantities = $total_quantities;
        }
        return $best_seller_products;
    }

    private function prepareProductCards($products)
    {
        $today = now();

        foreach ($products as $product) {
            foreach ($product->detailed_products as $detailed_product) {
                $discountPercentage = 0;
                foreach ($detailed_product->product_discounts as $product_discount) {
                    if ($product_discount->discount->is_currently_active()) {
                        $discountPercentage += $product_discount->discount->percentage;
                    }
                }
                $detailed_product->total_discount_percentage = $discountPercentage;
            }

            $detailed_product =
                $product->detailed_products
                ->sortByDesc(function ($detailed_product) use ($today) {
                    return $detailed_product->product_discounts
                        ->where('discount.start_date', '<=', $today)
                        ->where('discount.end_date', '>=', $today)
                        ->sum('discount.percentage');
                })
                ->first() ?? $product->detailed_products->first();

            if ($detailed_product && isset($detailed_product->images->first()->url)) {
                $detailed_product->image = $detailed_product->images->first()->url;
                $detailed_product->color_name = $detailed_product->color?->name;
                $detailed_product->color_code = $detailed_product->color?->code;
                $detailed_product->setRelation('images', null);
            }

            $product->detailed_product = $detailed_product;
            $product->price = $detailed_product?->original_price ?? 0;
            $product->total_quantities = $product->detailed_products->sum('quantities');
            $product->color_options = $product->detailed_products
                ->map(fn ($detail) => [
                    'name' => $detail->color?->name,
                    'code' => $detail->color?->code,
                ])
                ->unique('name')
                ->values();
            $product->setRelation('detailed_products', null);
        }

        return $products;
    }

    private function getProductsByCategoryName(string $categoryName, int $limit = 4)
    {
        $products = Product::with([
            'category',
            'brand',
            'detailed_products' => function ($query) {
                $query->where('is_deleted', 0)->with('images', 'color', 'product_discounts.discount');
            },
        ])
            ->where('is_deleted', false)
            ->whereHas('category', function ($query) use ($categoryName) {
                $query->where('name', $categoryName);
            })
            ->has('detailed_products')
            ->orderByDesc('amount_sold')
            ->take($limit)
            ->get();

        return $this->prepareProductCards($products);
    }

    public function index(SeoService $seo)
    {
        $deal_products = $this->getDealProducts();
        $latest_products = $this->getLatestProducts();
        $best_seller_products = $this->getBestSellerProducts();
        $featured_sections = collect(['Office Chair', 'Hanging Chair', 'Folding Chair'])
            ->map(function ($categoryName) {
                $category = Category::where('name', $categoryName)->first();

                return [
                    'title' => $categoryName,
                    'category_id' => $category?->category_id,
                    'products' => $this->getProductsByCategoryName($categoryName),
                ];
            });

        $data = [
            'page' => 'Home',
            'deal_products' => $deal_products,
            'latest_products' => $latest_products,
            'best_seller_products' => $best_seller_products,
            'featured_sections' => $featured_sections,
            'seo' => $seo->defaultMeta([
                'title' => 'Furemir Furniture | Modern Home Interiors',
                'description' => 'Shop curated furniture for living rooms, bedrooms, dining rooms and home offices at Furemir.',
                'canonical' => route('user'),
            ]),
            'structuredData' => [
                $seo->organizationSchema(),
                $seo->websiteSchema(),
            ],
        ];
        if (session()->has('url.intended')) {
            $url = session()->get('url.intended');
            session()->forget('url.intended');
            return redirect($url);
        }
        return view('pages.dashboard.index', $data);
    }

    public function shop(SeoService $seo)
    {
        $category = request()->query('category');
        $productType = request()->query('type');
        $color = request()->query('color');
        $tag = request()->query('tag');
        $search = request()->query('search');
        $sorted_by = request()->query('sorted_by');


        $price_from = request()->query('price_from');
        $price_to = request()->query('price_to');


        // query
        $query = Product::with(
            [
                'category',
                'product_type',
                'brand',
                'product_tags.tag',
                'detailed_products' => function ($query) {
                    $query->where('is_deleted', 0)->with('images', 'color', 'product_discounts.discount');
                },
            ]
        )->where('is_deleted', false)->has('detailed_products');



        if ($price_from !== null && $price_to !== null) {
            $query->whereHas('detailed_products', function ($query) use ($price_from, $price_to) {
                $query->where('is_deleted', 0)
                    ->whereBetween('original_price', [$price_from, $price_to]);
            });
        }

        // If category is not 'all', filter by category_id
        if ($category !== 'all' && $category !== null) {
            $query->where('category_id', $category);
        }
        if ($productType !== 'all' && $productType !== null) {
            $query->where('product_type_id', $productType);
        }
        // If color is not 'all', filter by color_id
        if ($color !== 'all' && $color !== null) {
            $colorIds = explode(',', $color);
            $query->whereHas('detailed_products', function ($query) use ($colorIds) {
                $query->whereIn('color_id', $colorIds);
            });
        }

        // If tag is not 'all', filter by tag
        if ($tag !== 'all' && $tag !== null) {
            $tagIds = explode(',', $tag);
            $query->whereHas('product_tags', function ($query) use ($tagIds) {
                $query->whereIn('tag_id', $tagIds);
            });
        }

        // If search is not null, filter by name
        if ($search !== null) {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('short_description', 'LIKE', '%' . $search . '%')
                    ->orWhere('material', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('product_type', function ($typeQuery) use ($search) {
                        $typeQuery->where('name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('product_tags.tag', function ($tagQuery) use ($search) {
                        $tagQuery->where('name', 'LIKE', '%' . $search . '%');
                    });
            });
        }
        // sorted 
        if ($sorted_by === 'latest') {
            $query->orderBy('created_at', 'desc');
        }
        if ($sorted_by === 'oldest') {
            $query->orderBy('created_at', 'asc');
        }
        $products = $query->paginate(9); // 9 elements per page
        foreach ($products as $product) {
            $total_discount_percentage = 0;
            foreach ($product->detailed_products as $detailed_product) {
                foreach ($detailed_product->product_discounts as $product_discount) {
                    if ($product_discount->discount->is_currently_active()) {
                        $total_discount_percentage += $product_discount->discount->percentage;
                    }
                }
                $detailed_product->total_discount_percentage = $total_discount_percentage;
            }
        }

        $today = now();
        foreach ($products as $product) {
            $detailed_product =
                $product->detailed_products
                ->sortByDesc(function ($detailed_product) use ($today) {
                    return $detailed_product->product_discounts
                        ->where('discount.start_date', '<=', $today)
                        ->where('discount.end_date', '>=', $today)
                        ->sum('discount.percentage');
                })
                ->first() ?? $product->detailed_products->first();

            if (isset($detailed_product->images->first()->url)) {
                $detailed_product->image = $detailed_product->images->first()->url;
                $detailed_product->color_name = $detailed_product->color?->name;
                $detailed_product->color_code = $detailed_product->color?->code;
                $detailed_product->setRelation('images', null);
            }
            $product->detailed_product = $detailed_product;
            $product->price = $detailed_product->original_price;
            $total_quantities = $product->detailed_products->sum('quantities');
            $product->setRelation('detailed_products', null);
            $product->total_quantities = $total_quantities;
        }
        if ($sorted_by === 'price_asc' || $sorted_by === 'price_desc') {
            if ($sorted_by === 'price_asc') {
                $sortedItems = collect($products->items())->sortBy(function ($product) {
                    return $product->price;
                })->values();
            } else if ($sorted_by === 'price_desc') {
                $sortedItems = collect($products->items())->sortByDesc(function ($product) {
                    return $product->price;
                })->values();
            }
            $products = new LengthAwarePaginator(
                $sortedItems,
                $products->total(),
                $products->perPage(),
                $products->currentPage(),
                ['path' => Paginator::resolveCurrentPath()]
            );
        }

        $categories = Category::orderBy('index', 'asc')->get();
        $productTypes = ProductType::withCount([
            'products' => fn ($query) => $query->where('is_deleted', false),
        ])->orderBy('index')->orderBy('name')->get();
        $selectedCategory = ($category !== 'all' && $category !== null)
            ? $categories->firstWhere('category_id', (int) $category)
            : null;
        $selectedProductType = ($productType !== 'all' && $productType !== null)
            ? $productTypes->firstWhere('product_type_id', (int) $productType)
            : null;
        $shopSeo = $selectedCategory
            ? $seo->categoryMeta($selectedCategory)
            : $seo->defaultMeta([
                'title' => 'Shop Furniture | Furemir',
                'description' => 'Browse Furemir furniture by category, color, tag and price range.',
                'canonical' => route('shop'),
            ]);
        $data = [
            'page' => 'Shop',
            'categories' => $categories,
            'product_types' => $productTypes,
            'colors' => Color::all(),
            'selected_category' => $category ?? 'all',
            'selected_product_type' => $productType ?? 'all',
            'selected_product_type_model' => $selectedProductType,
            'selected_colors' => $colorIds ?? [],
            'selected_tags' => $tagIds ?? [],
            'tags' => Tag::all(),
            'products' => $products,
            'search' => $search ?? '',
            'sorted_by' => $sorted_by ?? 'default',
            'seo' => $shopSeo,
            'structuredData' => [
                $seo->breadcrumbSchema([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => $selectedCategory?->name ?? 'Shop', 'url' => $shopSeo['canonical']],
                ]),
            ],
        ];

        return view('pages.shop.index', $data);
    }
    public function product_details(Request $request, ProductRecommendationService $recommendations, SeoService $seo)
    {
        $identifier = (string) $request->route('product');
        $productQuery = Product::with(['category', 'brand', 'product_tags.tag', 'detailed_products' => function ($query) {
            $query->where('is_deleted', 0)->with('images', 'color', 'product_discounts.discount');
        }])
            ->where('is_deleted', false);

        if (ctype_digit($identifier)) {
            $product = (clone $productQuery)->where('product_id', (int) $identifier)->first();

            if ($product && $product->slug) {
                return redirect()->route('products.show', $product->slug, 301);
            }
        } else {
            $product = (clone $productQuery)->where('slug', $identifier)->first();
        }

        if (!isset($product)) {
            $product = null;
        }

        if ($product) {
            $reviews = Review::approved()
                ->where('product_id', $product->product_id)
                ->with(['user', 'images'])
                ->latest('approved_at')
                ->paginate(5, ['*'], 'reviews_page')
                ->withQueryString();

            $ratingRows = Review::approved()
                ->where('product_id', $product->product_id)
                ->select('rating', DB::raw('count(*) as total'))
                ->groupBy('rating')
                ->pluck('total', 'rating');

            $reviewCount = (int) $ratingRows->sum();
            $averageRating = $reviewCount > 0
                ? round(Review::approved()->where('product_id', $product->product_id)->avg('rating'), 1)
                : 0;

            $distribution = collect(range(5, 1))->mapWithKeys(function ($rating) use ($ratingRows, $reviewCount) {
                $total = (int) ($ratingRows[$rating] ?? 0);

                return [$rating => [
                    'total' => $total,
                    'percent' => $reviewCount > 0 ? round(($total / $reviewCount) * 100) : 0,
                ]];
            });

            $data = [
                'page' => 'Product Details',
                'product' => $product,
                'reviews' => $reviews,
                'reviewSummary' => [
                    'average' => $averageRating,
                    'count' => $reviewCount,
                    'distribution' => $distribution,
                ],
                'relatedProducts' => $recommendations->relatedProducts($product),
                'completeRoomProducts' => $recommendations->completeRoom($product),
                'recentlyViewedProducts' => $recommendations->recentlyViewed($request, (int) $product->product_id),
                'seo' => $seo->productMeta($product, [
                    'average' => $averageRating,
                    'count' => $reviewCount,
                ]),
                'structuredData' => [
                    $seo->productSchema($product, [
                        'average' => $averageRating,
                        'count' => $reviewCount,
                    ]),
                    $seo->breadcrumbSchema([
                        ['name' => 'Home', 'url' => url('/')],
                        ['name' => 'Shop', 'url' => route('shop')],
                        ['name' => $product->category?->name ?? 'Furniture', 'url' => route('shop', ['category' => $product->category_id])],
                        ['name' => $product->name, 'url' => $product->publicUrl()],
                    ]),
                ],
            ];
            $recommendations->recordRecentlyViewed($request, (int) $product->product_id);
            return view('pages.product_details.index', $data);
        }
        abort(404);
    }
    public function cart()
    {
        $data = ['page' => 'Cart'];
        return view('pages.cart.index', $data);
    }
    public function checkout()
    {
        if (Auth::check()) {
            $data = ['page' => 'Checkout'];
            return view('pages.checkout.index', $data);
        } else {
            session()->put('url.intended', '/checkout');
            return redirect()->route('login');
        }
    }
    public function about()

    {
        $data = [
            'page' => 'About us'
        ];
        return view('pages.about.index', $data);
    }

    public function contact()
    {
        $data = [
            'page' => 'Contact us'
        ];
        return view('pages.contact.index', $data);
    }
    public function account()
    {
        $data = [
            'page' => 'My account'
        ];
        return view('pages.account.index', $data);
    }

    public function change_password()
    {
        return view('auth.change-password');
    }

    public function handle_checkout_order(Request $request)
    {
        $order_id = $request->route('order_id');
        $order = Order::where('order_id', $order_id)->where('customer_id', Auth::id())->with('order_details')->first();
        $data = [
            'page' => 'Checkout Success',
        ];
        if ($order) {
            $order->get_status = $order->get_status();
            $data['order'] = $order;
        }

        return view('pages.checkout.success', $data);
    }
    public function my_orders()
    {
        $orders = Order::where('customer_id', Auth::id())->with('order_details')->orderBy('created_at', 'desc')->get();

        foreach ($orders as $order) {
            $order->date_time = $order->howmanydaysago();
        }

        $data = [
            'page' => 'My orders',
            'orders' => $orders,
        ];
        return view('pages.myorders.index', $data);
    }
    public function my_detailed_order()
    {
        $order_id = request()->route('order_id');
        $order = Order::where('order_id', $order_id)
            ->where('customer_id', Auth::id())
            ->with(['order_details.detailed_product.images', 'order_details.detailed_product.color'])
            ->first();

        if ($order) {
            $order->get_status = $order->get_status();
            $reviewKeys = $order->order_details
                ->map(fn ($detail) => Review::orderDetailKey($detail->order_id, $detail->sku))
                ->values();

            $reviews = Review::whereIn('order_detail_id', $reviewKeys)
                ->with('images')
                ->get()
                ->keyBy('order_detail_id');

            foreach ($order->order_details as $detail) {
                $detail->setRelation('review', $reviews->get(Review::orderDetailKey($detail->order_id, $detail->sku)));
            }

            $data = [
                'page' => 'My order details',
                'order' => $order,
            ];
        } else {
            $data = [
                'page' => 'My order details',
                'order' => null,
            ];
        }

        return view('pages.myorders.detailed-order', $data);
    }



    public function asyncCart(Request $request)
    {
        $cart = json_decode($request->query('cart', '[]'));

        if (!is_array($cart)) {
            $cart = [];
        }

        $new_cart = [];
        foreach ($cart as $item) {
            $detailed_product = ProductDetail::where('sku', $item->sku)
                ->with('images', 'color', 'product', 'product_discounts.discount')
                ->first();
            if ($detailed_product) {
                $total_discount_percentage = 0;
                foreach ($detailed_product->product_discounts as $product_discount) {
                    if ($product_discount->discount->is_currently_active()) {
                        $total_discount_percentage += $product_discount->discount->percentage;
                    }
                }
                if (isset($detailed_product->images->first()->url)) {
                    $item->image = $detailed_product->images->first()->url;
                }
                $item->unit_price = $detailed_product->original_price -  $detailed_product->original_price * $total_discount_percentage / 100;
                $item->name = $detailed_product->product?->name ?? $detailed_product->name;
                $item->product_id = $detailed_product->product_id;
                $item->color_name = $detailed_product->color?->name;
                $item->color_hex = $detailed_product->color?->code;
                $new_cart[] = $item;
            }
        }

        return response()->json(['cart' => $new_cart]);
    }

    public function profile(Request $request)
    {
        $user = User::where('user_id', Auth::id())->first();

        if ($user) {
            $listaddress = Address::where('user_id', $user->user_id)->get();
            $data = [
                'page' => 'Profile',
                'user' => $user,
                'address_cards' => $listaddress
            ];
            return view("pages.account.profile", $data);
        }
        abort(404);
    }

    public function update_profile(UpdateEmployee $request)
    {
        $user = User::where('user_id', Auth::id())->first();
        $user->update([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'gender' => ($request->input('gender') == 'male' || $request->input('gender') == null),
            'birth_date' => $request->input('birth_date'),
        ]);

        $address = Address::where('user_id', $user->user_id)->where('is_default', 1)->first();
        if ($address) {
            $address->update([
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
            ]);
        } else {
            Address::create([
                'user_id' => $user->user_id,
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
                'receiver_name' => $request->input('first_name') . ' ' . $request->input('last_name'),
                'is_default' => 1,
            ]);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $path = config('app.url') . 'storage/' . $file->store('uploads/avatars', 'public');
            $user->update(['avatar' => $path]);
        }
        return response()->json(['message' => 'Profile updated successfully!']);
    }
}
