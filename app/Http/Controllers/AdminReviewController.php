<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUpdateReviewRequest;
use App\Models\Review;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product', 'order', 'productDetail.images', 'productDetail.color', 'images', 'approver'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->integer('rating'));
        }

        if ($request->filled('q')) {
            $search = trim($request->string('q'));
            $query->where(function ($builder) use ($search) {
                $builder->where('content', 'LIKE', '%' . $search . '%')
                    ->orWhere('sku', 'LIKE', '%' . $search . '%')
                    ->orWhere('order_detail_id', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'LIKE', '%' . $search . '%')
                            ->orWhere('first_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        return view('admin.reviews.index', [
            'page' => 'Reviews',
            'reviews' => $query->paginate(12)->withQueryString(),
            'statuses' => Review::STATUSES,
            'stats' => [
                'total' => Review::count(),
                'pending' => Review::where('status', Review::STATUS_PENDING)->count(),
                'approved' => Review::where('status', Review::STATUS_APPROVED)->count(),
                'rejected' => Review::where('status', Review::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    public function show(Review $review)
    {
        $review->load(['user', 'product', 'order', 'productDetail.images', 'productDetail.color', 'images', 'approver']);

        return view('admin.reviews.show', [
            'page' => 'Review Details',
            'review' => $review,
            'statuses' => Review::STATUSES,
        ]);
    }

    public function update(AdminUpdateReviewRequest $request, Review $review)
    {
        $data = $request->validated();
        $review->status = $data['status'];
        $review->admin_note = $data['admin_note'] ?? null;

        if ($data['status'] === Review::STATUS_APPROVED) {
            $review->approved_by = $request->user()->getKey();
            $review->approved_at = now();
        } else {
            $review->approved_by = null;
            $review->approved_at = null;
        }

        $review->save();

        return back()->with('success', 'Review status was updated.');
    }
}
