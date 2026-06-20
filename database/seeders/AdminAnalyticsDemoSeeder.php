<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\ReceivingReport;
use App\Models\ReceivingReportDetails;
use App\Models\SiteVisit;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminAnalyticsDemoSeeder extends Seeder
{
    private const DEMO_PREFIX = '[demo-analytics]';
    private const CUSTOMER_DOMAIN = 'furemir-demo.test';

    public function run(): void
    {
        DB::transaction(function () {
            $this->clearPreviousDemoData();

            $admin = User::where('is_staff', true)->orderBy('user_id')->first();
            $customers = $this->seedCustomers();
            $details = ProductDetail::with('product.category')->where('is_deleted', false)->get();

            if ($customers->isEmpty() || $details->isEmpty()) {
                return;
            }

            $orders = $this->seedOrders($customers, $details, $admin?->user_id);
            $this->seedReceivingReports($details, $admin?->user_id);
            $this->seedTraffic($customers, $orders);
            $this->refreshProductAnalytics();
            $this->shapeStockForAnalytics($details);
        });
    }

    private function clearPreviousDemoData(): void
    {
        if (Schema::hasTable('site_visits')) {
            SiteVisit::where('session_id', 'like', 'demo-%')->delete();
        }

        $demoOrderIds = Order::where('note', 'like', self::DEMO_PREFIX . '%')->pluck('order_id');
        if ($demoOrderIds->isNotEmpty()) {
            DB::table('order_details')->whereIn('order_id', $demoOrderIds)->delete();
            Order::whereIn('order_id', $demoOrderIds)->delete();
        }

        $demoUserIds = User::where('email', 'like', 'demo.customer.%@' . self::CUSTOMER_DOMAIN)->pluck('user_id');
        if ($demoUserIds->isNotEmpty()) {
            Address::whereIn('user_id', $demoUserIds)->delete();
            User::whereIn('user_id', $demoUserIds)->delete();
        }
    }

    private function seedCustomers()
    {
        $firstNames = ['An', 'Binh', 'Chi', 'Dung', 'Giang', 'Ha', 'Hung', 'Khanh', 'Lan', 'Linh', 'Long', 'Mai', 'Minh', 'Nam', 'Ngoc', 'Phuc', 'Quan', 'Son', 'Thao', 'Trang'];
        $lastNames = ['Nguyen', 'Tran', 'Le', 'Pham', 'Hoang', 'Phan', 'Vu', 'Dang', 'Bui', 'Do'];
        $cities = ['Ho Chi Minh City', 'Ha Noi', 'Da Nang', 'Can Tho', 'Hai Phong', 'Nha Trang', 'Hue', 'Bien Hoa'];
        $customers = collect();

        for ($i = 1; $i <= 64; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $createdAt = now()->subDays(random_int(0, 180))->subHours(random_int(0, 23));

            $customer = User::create([
                'email' => 'demo.customer.' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '@' . self::CUSTOMER_DOMAIN,
                'password' => Hash::make('123123'),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'avatar' => config('app.url') . 'storage/defaults/default_avatar.jpg',
                'birth_date' => now()->subYears(random_int(21, 58))->subDays(random_int(0, 365))->toDateString(),
                'gender' => (bool) random_int(0, 1),
                'is_staff' => false,
                'is_verified' => true,
                'is_active' => random_int(1, 100) <= 94,
            ]);
            $customer->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            $city = $cities[array_rand($cities)];
            Address::create([
                'user_id' => $customer->user_id,
                'receiver_name' => $customer->full_name(),
                'address' => random_int(12, 890) . ' ' . $this->streetName() . ', ' . $city,
                'phone_number' => '09' . random_int(10000000, 99999999),
                'is_default' => true,
            ]);

            if (random_int(1, 100) <= 28) {
                Address::create([
                    'user_id' => $customer->user_id,
                    'receiver_name' => $customer->full_name(),
                    'address' => random_int(12, 890) . ' ' . $this->streetName() . ', ' . $cities[array_rand($cities)],
                    'phone_number' => '03' . random_int(10000000, 99999999),
                    'is_default' => false,
                ]);
            }

            $customers->push($customer);
        }

        return $customers;
    }

    private function seedOrders($customers, $details, ?int $adminId)
    {
        $orders = collect();
        $statusPool = [0, 0, 1, 1, 1, 2, 2, 3, 3, 3, 3, 4];
        $orderTarget = 260;

        for ($i = 1; $i <= $orderTarget; $i++) {
            $customer = $customers->random();
            $address = Address::where('user_id', $customer->user_id)->orderByDesc('is_default')->first();
            $createdAt = $this->weightedOrderDate();
            $status = $statusPool[array_rand($statusPool)];
            $isPaid = $status !== 4 && random_int(1, 100) <= ($status === 0 ? 42 : 88);
            $lineCount = random_int(1, 4);
            $selectedDetails = $details->random(min($lineCount, $details->count()));
            $selectedDetails = $selectedDetails instanceof ProductDetail ? collect([$selectedDetails]) : $selectedDetails;
            $total = 0;

            $order = Order::create([
                'total_price' => 0,
                'is_paid' => $isPaid,
                'status' => $status,
                'receiver_name' => $address?->receiver_name ?? $customer->full_name(),
                'address' => $address?->address ?? 'Demo address',
                'phone_number' => $address?->phone_number ?? '0900000000',
                'customer_id' => $customer->user_id,
                'created_by' => $status === 0 ? null : $adminId,
                'note' => self::DEMO_PREFIX . ' order #' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            foreach ($selectedDetails as $detail) {
                $quantity = random_int(1, $detail->original_price < 3000000 ? 4 : 2);
                $discount = random_int(1, 100) <= 24 ? random_int(5, 14) / 100 : 0;
                $unitPrice = round((float) $detail->original_price * (1 - $discount), 0);
                $total += $unitPrice * $quantity;

                DB::table('order_details')->insert([
                    'order_id' => $order->order_id,
                    'sku' => $detail->sku,
                    'quantities' => $quantity,
                    'unit_price' => $unitPrice,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $order->forceFill(['total_price' => $total])->save();
            $orders->push($order->fresh());
        }

        return $orders;
    }

    private function seedReceivingReports($details, ?int $adminId): void
    {
        if (!$adminId || ReceivingReport::count() >= 25) {
            return;
        }

        $this->seedSuppliersIfNeeded();
        $suppliers = Supplier::all();
        $needed = 25 - ReceivingReport::count();

        for ($i = 0; $i < $needed; $i++) {
            $createdAt = now()->subDays(random_int(0, 120))->subHours(random_int(0, 23));
            $selectedDetails = $details->random(min(random_int(2, 5), $details->count()));
            $selectedDetails = $selectedDetails instanceof ProductDetail ? collect([$selectedDetails]) : $selectedDetails;
            $total = 0;

            $report = ReceivingReport::create([
                'total_price' => 0,
                'supplier_id' => $suppliers->random()->supplier_id,
                'created_by' => $adminId,
            ]);
            $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            foreach ($selectedDetails as $detail) {
                $quantity = random_int(6, 32);
                $unitPrice = round((float) $detail->original_price * random_int(48, 68) / 100, 0);
                $total += $quantity * $unitPrice;

                DB::table('receiving_report_details')->insert([
                    'receiving_report_id' => $report->receiving_report_id,
                    'sku' => $detail->sku,
                    'quantities' => $quantity,
                    'unit_price' => $unitPrice,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $report->forceFill(['total_price' => $total])->save();
        }
    }

    private function seedSuppliersIfNeeded(): void
    {
        if (Supplier::count() > 0) {
            return;
        }

        collect([
            ['name' => 'Furemir Supply Hub', 'description' => 'Primary furniture warehouse for demo analytics.', 'address' => '12 Vo Van Kiet, Ho Chi Minh City', 'phone_number' => '0901001001'],
            ['name' => 'Oak & Rattan Co.', 'description' => 'Wood and rattan furniture supplier.', 'address' => '88 Nguyen Trai, Ha Noi', 'phone_number' => '0901001002'],
            ['name' => 'Studio Seating Works', 'description' => 'Office seating and ergonomic chair supplier.', 'address' => '45 Dien Bien Phu, Da Nang', 'phone_number' => '0901001003'],
            ['name' => 'PatioCraft Logistics', 'description' => 'Outdoor and patio furniture supplier.', 'address' => '21 Le Loi, Can Tho', 'phone_number' => '0901001004'],
        ])->each(fn ($supplier) => Supplier::create($supplier));
    }

    private function seedTraffic($customers, $orders): void
    {
        $sources = ['direct', 'google', 'facebook', 'instagram', 'email', 'zalo', 'tiktok'];
        $devices = ['desktop', 'mobile', 'tablet'];
        $browsers = ['Chrome', 'Safari', 'Edge', 'Firefox'];
        $urls = ['/', '/shop', '/about', '/contact', '/cart'];
        Product::where('is_deleted', false)->inRandomOrder()->limit(28)->pluck('product_id')->each(function ($productId) use (&$urls) {
            $urls[] = '/products/' . $productId;
        });

        $rows = [];
        for ($i = 1; $i <= 6800; $i++) {
            $createdAt = $this->weightedVisitDate();
            $source = $sources[array_rand($sources)];
            $customer = random_int(1, 100) <= 38 ? $customers->random() : null;
            $convertedOrder = null;

            if ($customer && random_int(1, 100) <= 17) {
                $convertedOrder = $orders->where('customer_id', $customer->user_id)->where('created_at', '>=', $createdAt)->first();
            }

            $rows[] = [
                'session_id' => 'demo-' . Str::uuid(),
                'user_id' => $customer?->user_id,
                'converted_order_id' => $convertedOrder?->order_id,
                'url' => $urls[array_rand($urls)],
                'referrer' => $this->referrerForSource($source),
                'source' => $source,
                'device' => $devices[array_rand($devices)],
                'browser' => $browsers[array_rand($browsers)],
                'ip_address' => '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254),
                'metadata' => json_encode([
                    'campaign' => $source === 'direct' ? null : 'demo-' . $source . '-furniture',
                    'city' => ['Ho Chi Minh City', 'Ha Noi', 'Da Nang', 'Can Tho'][array_rand(['Ho Chi Minh City', 'Ha Noi', 'Da Nang', 'Can Tho'])],
                ]),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) === 500) {
                DB::table('site_visits')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('site_visits')->insert($rows);
        }
    }

    private function refreshProductAnalytics(): void
    {
        Product::query()->update(['amount_sold' => 0]);

        DB::table('order_details')
            ->join('product_details', 'order_details.sku', '=', 'product_details.sku')
            ->select('product_details.product_id', DB::raw('SUM(order_details.quantities) as sold'))
            ->groupBy('product_details.product_id')
            ->get()
            ->each(function ($row) {
                Product::where('product_id', $row->product_id)->update(['amount_sold' => (int) $row->sold]);
            });
    }

    private function shapeStockForAnalytics($details): void
    {
        $details->each(function (ProductDetail $detail) {
            if ($detail->quantities < 8) {
                return;
            }

            $detail->update(['quantities' => random_int(8, 74)]);
        });

        $details->shuffle()->take(10)->values()->each(function (ProductDetail $detail, int $index) {
            $detail->update(['quantities' => [0, 1, 2, 3, 4, 5, 5, 3, 2, 1][$index] ?? 4]);
        });
    }

    private function weightedOrderDate(): Carbon
    {
        $bucket = random_int(1, 100);
        $daysAgo = match (true) {
            $bucket <= 38 => random_int(0, 14),
            $bucket <= 72 => random_int(15, 60),
            default => random_int(61, 180),
        };

        return now()
            ->subDays($daysAgo)
            ->setHour(random_int(8, 22))
            ->setMinute(random_int(0, 59))
            ->setSecond(random_int(0, 59));
    }

    private function weightedVisitDate(): Carbon
    {
        $bucket = random_int(1, 100);
        $daysAgo = match (true) {
            $bucket <= 42 => random_int(0, 14),
            $bucket <= 78 => random_int(15, 60),
            default => random_int(61, 180),
        };

        return now()
            ->subDays($daysAgo)
            ->setHour(random_int(7, 23))
            ->setMinute(random_int(0, 59))
            ->setSecond(random_int(0, 59));
    }

    private function streetName(): string
    {
        $streets = ['Nguyen Hue', 'Le Loi', 'Tran Hung Dao', 'Vo Van Kiet', 'Dien Bien Phu', 'Pham Van Dong', 'Nguyen Trai', 'Ly Thuong Kiet'];

        return $streets[array_rand($streets)] . ' Street';
    }

    private function referrerForSource(string $source): ?string
    {
        return match ($source) {
            'google' => 'https://google.com/search?q=furniture',
            'facebook' => 'https://facebook.com/furemir',
            'instagram' => 'https://instagram.com/furemir',
            'email' => 'https://mail.google.com',
            'zalo' => 'https://zalo.me/furemir',
            'tiktok' => 'https://tiktok.com/@furemir',
            default => null,
        };
    }
}
