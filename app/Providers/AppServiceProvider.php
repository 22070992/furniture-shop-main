<?php

namespace App\Providers;

use App\Models\Review;
use App\Models\VisualFeedback;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'components.header', 'pages.*'], function ($view) {
            $wishlistProductIds = collect();
            $wishlistCount = 0;
            $compareProductIds = collect(session('compare_product_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if (auth()->check() && Schema::hasTable('wishlist_items')) {
                $wishlistProductIds = WishlistItem::where('user_id', auth()->id())
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->values();
                $wishlistCount = $wishlistProductIds->count();
            }

            $view->with([
                'wishlistProductIds' => $wishlistProductIds,
                'wishlistCount' => $wishlistCount,
                'compareProductIds' => $compareProductIds,
                'compareCount' => $compareProductIds->count(),
            ]);
        });

        View::composer('admin.components.navbar', function ($view) {
            $openFeedbackCount = 0;
            $pendingReviewCount = 0;

            if (Schema::hasTable('visual_feedbacks')) {
                $openFeedbackCount = VisualFeedback::whereNotIn('status', ['Resolved', 'Closed'])->count();
            }

            if (Schema::hasTable('reviews')) {
                $pendingReviewCount = Review::where('status', Review::STATUS_PENDING)->count();
            }

            $view->with([
                'openFeedbackCount' => $openFeedbackCount,
                'pendingReviewCount' => $pendingReviewCount,
            ]);
        });
    }
}
