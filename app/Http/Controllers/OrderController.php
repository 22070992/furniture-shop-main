<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrder;
use App\Http\Requests\CheckoutOrder;
use App\Http\Requests\CreateDetailedOrder;
use App\Http\Requests\CreateOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\User;
use App\Models\Warranty;
use App\Services\Payments\VnpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderController extends Controller
{
    public function index()
    {
        // query parameters
        $search = request()->query('search');
        $sort = request()->query('sort');
        $type = request()->query('type');
        $day_first = request()->query('dayfirst');
        $day_last = request()->query('daylast');

        // filter orders by start_date, end_date
        if (!isset($day_first)) {
            $day_first = Carbon::create(1900, 1, 1);
        }

        if (isset($day_last)) {
            $day_last = Carbon::parse($day_last)->addDay();
        } else {
            $day_last = Carbon::tomorrow();
        }

        $query = Order::whereBetween('created_at', [$day_first, $day_last])
            ->where(function ($query) use ($search) {
                $query->where('order_id', 'LIKE', '%' . $search . '%')
                    ->orWhere('receiver_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('address', 'LIKE', '%' . $search . '%');
            });

        if ($type != 'all' && $type != null) {
            $query = $query->where('status', $type);
        }
        // sort orders
        if ($sort == 'oldest') {
            $query = $query->orderBy('created_at', 'asc');
        } else if ($sort == 'latest' || $sort == null) {
            $query = $query->orderBy('created_at', 'desc');
        } else if ($sort == 'price_asc') {
            $query = $query->orderBy('total_price', 'asc');
        } else if ($sort == 'price_desc') {
            $query = $query->orderBy('total_price', 'desc');
        }
        $orders = $query->paginate(6); // 6 orders per page

        $data = [
            'page' => 'Orders',
            'orders' =>  $orders,
            'customers' => User::where('is_staff', false)->get(),
            'search' => $search,
            'type' => $type,
            'sort' => $sort,
            'dayfirst' => $day_first,
            'daylast' => $day_last,
        ];
        return view('admin.orders.index', $data);
    }

    public function create(CreateOrder $request)
    {
        $isPaid = $request->input('paid') === 'on' ? true : false;
        $order_data = [
            'total_price' => 0,
            'is_paid' => $isPaid,
            'payment_method' => Order::PAYMENT_METHOD_COD,
            'payment_status' => $isPaid ? Order::PAYMENT_STATUS_PAID : Order::PAYMENT_STATUS_UNPAID,
            'paid_at' => $isPaid ? now() : null,
            'status' => $request->input('status'),
            'receiver_name' => $request->input('receiver_name'),
            'address' => $request->input('address'),
            'phone_number' => $request->input('phone_number'),
            'customer_id' => $request->input('customer_id') == -1 ? null : $request->input('customer_id'),
            'created_by' => Auth::user()->user_id,
        ];
        $order = Order::create($order_data);
        return ['message' => 'Created order successfully!', 'order' => $order];
    }

    public function update(Request $request)
    {

        $order_id = $request->route('order_id');
        $order = Order::where('order_id', $order_id)->first();
        if ($order) {
            $isPaid = $request->input('paid') === 'on' ? true : false;

            $note = $order->note;

            // create warranty when delivered and paid
            if ($request->input('status') == '3' && $isPaid) {
                if ($order->status != 3 || $order->is_paid == false) {
                    $order_details = OrderDetail::where('order_id', $order_id)->get();
                    $start_date = now();
                    foreach ($order_details as $order_detail) {
                        $detailed_product = ProductDetail::where('sku', $order_detail->sku)->first();
                        $end_date = $start_date->copy()->addMonths($detailed_product->warranty_month);
                        Warranty::create([
                            'order_id' => $order_id,
                            'sku' => $order_detail->sku,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'description' => 'auto create',
                        ]);
                    }
                }
            } else {
                if ($request->input('status') == '4' && $order->status != '4') {
                    $admin = User::where('user_id', Auth::id())->first();
                    $note =  $order->note . '<br>* Cancelled by employee: <strong><a href="/admin/employee/' . $admin->user_id . '/details">' . $admin->full_name() . '<a/></strong>';
                }
                $warranties = Warranty::where('order_id', $order_id)->get();
                foreach ($warranties as $warranty) {
                    $warranty->delete();
                }
            }

            $order->update([
                'is_paid' => $isPaid,
                'payment_status' => $isPaid ? Order::PAYMENT_STATUS_PAID : Order::PAYMENT_STATUS_UNPAID,
                'paid_at' => $isPaid ? ($order->paid_at ?? now()) : null,
                'status' => $request->input('status'),
                'receiver_name' => $request->input('receiver_name'),
                'address' => $request->input('address'),
                'phone_number' => $request->input('phone_number'),
                'customer_id' => $request->input('customer_id') == -1 ? null : $request->input('customer_id'),
                'note' => $note,
            ]);

            $order->howmanydaysago = $order->howmanydaysago();
            $order->money = $order->money_type();
            if ($order->created_at->diffInDays() < 7) {
                $order->new = true;
            }
            return ['message' => 'Update order successfully', 'order' => $order];
        } else {
            response()->json(['errors' => ['message' => ['Cannot find this order.']]], 400);
        }
    }

    public function search_orders_ajax()
    {
        // query parameters
        $search = request()->query('search');
        $sort = request()->query('sort');
        $type = request()->query('type');
        $day_first = request()->query('dayfirst');
        $day_last = request()->query('daylast');

        // filter orders by start_date, end_date
        if (!isset($day_first)) {
            $day_first = Carbon::create(1900, 1, 1);
        }

        if (isset($day_last)) {
            $day_last = Carbon::parse($day_last)->addDay();
        } else {
            $day_last = Carbon::tomorrow();
        }

        $query = Order::whereBetween('created_at', [$day_first, $day_last])
            ->where(function ($query) use ($search) {
                $query->where('order_id', 'LIKE', '%' . $search . '%')
                    ->orWhere('receiver_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('address', 'LIKE', '%' . $search . '%');
            });

        if ($type != 'all' && $type != null) {
            $query = $query->where('status', $type);
        }
        // sort orders
        if ($sort == 'oldest') {
            $query = $query->orderBy('created_at', 'asc');
        } else if ($sort == 'latest'  || $sort == null) {
            $query = $query->orderBy('created_at', 'desc');
        } else if ($sort == 'price_asc') {
            $query = $query->orderBy('total_price', 'asc');
        } else if ($sort == 'price_desc') {
            $query = $query->orderBy('total_price', 'desc');
        }
        $orders = $query->paginate(6); // 6 orders per page

        // serialize data
        foreach ($orders as $order) {
            $order->howmanydaysago = $order->howmanydaysago();
            $order->money = $order->money_type();
            if ($order->created_at->diffInDays() < 7) {
                $order->new = true;
            }
        }

        $admin = User::where('user_id', Auth::id())->first();
        return response()->json(['orders' => $orders, 'can_update' => $admin->can('update order')]);
    }


    public function details(Request $request)
    {
        $order_id = $request->route('order_id');
        $order = Order::where('order_id', $order_id)->with('employee.default_address')->first();
        $detailedOrders = $order->order_details()->with('detailed_product')->paginate(5); // 5 items per page

        $detailed_products = ProductDetail::where('is_deleted', 0)->paginate(5);
        $data = [
            'page' => 'Order Details',
            'order' => $order,
            'detailed_orders' => $detailedOrders,
            'detailed_products' => $detailed_products,
        ];
        return view('admin.orders.order_details', $data);
    }

    public function create_detailed_order(CreateDetailedOrder $request)
    {
        $order_id = $request->route('order_id');
        $sku = $request->input('sku');
        $quantities = $request->input('quantities');
        $unit_price = $request->input('unit_price');

        $order = Order::where('order_id', $order_id)->first();
        if (!$order) {
            return response()->json(['errors' => ['message' => ['Cannot find this order.']]], 400);
        }
        $detailed_order_exist = OrderDetail::where('order_id', $order_id)->where('sku', $sku)->first();
        if ($detailed_order_exist) {
            OrderDetail::where('order_id', $order_id)->where('sku', $sku)->update([
                'quantities' => $detailed_order_exist->quantities + $quantities,
                'unit_price' => $unit_price,
            ]);
            $detailed_product = ProductDetail::where('sku', $sku)->first();
            $detailed_product->decrement('quantities', $request->input('quantities'));
            Product::where('product_id', $detailed_product->product_id)->increment('amount_sold', $request->input('quantities'));
            $order->update(['total_price' => $order->total_price + $quantities * $unit_price]);
            return ['message' => 'Created order detail successfully!', 'detailed_order' => $detailed_order_exist];
        } else {
            $order_detail = OrderDetail::create([
                'order_id' => $order_id,
                'sku' => $sku,
                'quantities' => $quantities,
                'unit_price' => $unit_price,
            ]);
            $order->update(['total_price' => $order->total_price + $quantities * $unit_price]);
            $detailed_product = ProductDetail::where('sku', $sku)->first();
            $detailed_product->decrement('quantities', $request->input('quantities'));
            Product::where('product_id', $detailed_product->product_id)->increment('amount_sold', $request->input('quantities'));
            return ['message' => 'Created order detail successfully!', 'detailed_order' => $order_detail];
        }
    }


    public function checkout_order(CheckoutOrder $request, VnpayService $vnpay)
    {
        $checkout = $this->normalizeCheckoutItems(json_decode($request->input('checkout'), true) ?: []);
        $paymentMethod = $this->normalizePaymentMethod($request->input('payment_method'));

        try {
            $redirectUrl = DB::transaction(function () use ($checkout, $paymentMethod, $request, $vnpay) {
                $order = Order::create([
                    'total_price' => 0,
                    'is_paid' => false,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentMethod === Order::PAYMENT_METHOD_VNPAY
                        ? Order::PAYMENT_STATUS_PENDING
                        : Order::PAYMENT_STATUS_UNPAID,
                    'status' => 0,
                    'receiver_name' => $request->input('receiver_name'),
                    'address' => $request->input('address'),
                    'phone_number' => $request->input('phone_number'),
                    'note' => $request->input('note'),
                    'customer_id' => Auth::user()->user_id,
                    'created_by' => null,
                ]);

                $totalPrice = 0;

                foreach ($checkout as $item) {
                    $detailedProduct = ProductDetail::with(['product_discounts.discount', 'product'])
                        ->where('sku', $item['sku'])
                        ->where('is_deleted', 0)
                        ->lockForUpdate()
                        ->first();

                    if (!$detailedProduct || $detailedProduct->product?->is_deleted) {
                        throw ValidationException::withMessages([
                            'message' => ['Cannot find this product.'],
                        ]);
                    }

                    if ($detailedProduct->quantities < $item['quantities']) {
                        throw ValidationException::withMessages([
                            'message' => ['Not enough quantities for this product.'],
                        ]);
                    }

                    $unitPrice = $this->currentUnitPrice($detailedProduct);

                    OrderDetail::create([
                        'order_id' => $order->order_id,
                        'sku' => $item['sku'],
                        'quantities' => $item['quantities'],
                        'unit_price' => $unitPrice,
                    ]);

                    $detailedProduct->decrement('quantities', $item['quantities']);
                    Product::where('product_id', $detailedProduct->product_id)->increment('amount_sold', $item['quantities']);
                    $totalPrice += $item['quantities'] * $unitPrice;
                }

                $order->update(['total_price' => $totalPrice]);

                if ($paymentMethod === Order::PAYMENT_METHOD_VNPAY) {
                    $payment = Payment::create([
                        'order_id' => $order->order_id,
                        'provider' => Payment::PROVIDER_VNPAY,
                        'transaction_reference' => $vnpay->transactionReference((int) $order->order_id),
                        'amount' => $totalPrice,
                        'status' => Payment::STATUS_PENDING,
                        'order_info' => 'Thanh toan don hang #' . $order->order_id,
                    ]);

                    return $vnpay->createPaymentUrl($payment->load('order'), $request);
                }

                return route('checkout.handle', $order->order_id);
            });
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'errors' => [
                    'message' => ['VNPay is not configured yet. Please fill VNPAY_TMN_CODE and VNPAY_HASH_SECRET.'],
                ],
            ], 422);
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'redirect_url' => $redirectUrl,
                'payment_method' => $paymentMethod,
            ]);
        }

        return response($redirectUrl);
    }

    private function normalizeCheckoutItems(array $checkout): array
    {
        if (empty($checkout)) {
            throw ValidationException::withMessages([
                'message' => ['You have no product to checkout.'],
            ]);
        }

        $items = [];

        foreach ($checkout as $item) {
            $sku = (string) ($item['sku'] ?? '');
            $quantities = (int) ($item['quantities'] ?? 0);

            if ($sku === '' || $quantities < 1) {
                throw ValidationException::withMessages([
                    'message' => ['The checkout item is invalid.'],
                ]);
            }

            if (!isset($items[$sku])) {
                $items[$sku] = ['sku' => $sku, 'quantities' => 0];
            }

            $items[$sku]['quantities'] += $quantities;
        }

        return array_values($items);
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        if ($paymentMethod === 'vnpay') {
            return Order::PAYMENT_METHOD_VNPAY;
        }

        return Order::PAYMENT_METHOD_COD;
    }

    private function currentUnitPrice(ProductDetail $productDetail): int
    {
        $discountPercentage = $productDetail->product_discounts
            ->filter(fn ($productDiscount) => $productDiscount->discount?->is_currently_active())
            ->sum(fn ($productDiscount) => (float) $productDiscount->discount->percentage);

        $discountPercentage = min(100, max(0, $discountPercentage));
        $price = (float) $productDetail->original_price * (100 - $discountPercentage) / 100;

        return (int) round($price);
    }

    public function cancel_order(CancelOrder $request)
    {
        $order_id = $request->route('order_id');
        $order = Order::where('order_id', $order_id)->first();
        if ($order) {
            $order->update([
                'status' => 4,
                'note' => $order->note . '<br>* Cancelled by customer with reason: ' . $request->input('reason'),
            ]);
            return ['message' => 'Cancel order successfully', 'order' => $order];
        }
        return response()->json(['errors' => ['message' => ['Cannot find this order.']]], 400);
    }

    public function remove_detailed_order(Request $request)
    {
        $order_id = $request->route('order_id');
        $sku = $request->route('sku');
        $detailed_order = OrderDetail::where('order_id', $order_id)->where('sku', $sku)->first();
        if ($detailed_order) {
            Order::where('order_id', $order_id)->decrement('total_price', $detailed_order->quantities * $detailed_order->unit_price);
            DB::statement("DELETE FROM order_details WHERE order_id = :order_id AND sku = :sku", ['order_id' => $order_id, 'sku' => $sku]);
            return ['message' => 'Remove detailed order successfully'];
        }
        return ['message' => 'Can not find this detailed order'];
    }
}
