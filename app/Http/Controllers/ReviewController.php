<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $orderDetail = OrderDetail::with(['order', 'detailed_product'])
            ->where('order_id', $data['order_id'])
            ->where('sku', $data['sku'])
            ->firstOrFail();

        abort_if((int) $orderDetail->order->customer_id !== (int) $user->getKey(), 403);
        abort_if((int) $orderDetail->order->status !== 3, 403);
        abort_if((int) $orderDetail->detailed_product->product_id !== (int) $data['product_id'], 422);

        $orderDetailKey = Review::orderDetailKey($orderDetail->order_id, $orderDetail->sku);
        $existingReview = Review::where('order_detail_id', $orderDetailKey)->first();

        if ($existingReview && $existingReview->status !== Review::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'order_detail' => 'This order item has already been reviewed.',
            ]);
        }

        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $data, $user, $orderDetail, $existingReview, $orderDetailKey, &$storedPaths) {
                $approvedAt = now();

                if ($existingReview) {
                    foreach ($existingReview->images as $image) {
                        Storage::disk('public')->delete($image->image_path);
                    }

                    $existingReview->images()->delete();
                    $existingReview->update([
                        'rating' => $data['rating'],
                        'content' => $data['content'],
                        'status' => Review::STATUS_APPROVED,
                        'admin_note' => null,
                        'approved_by' => null,
                        'approved_at' => $approvedAt,
                    ]);

                    $review = $existingReview;
                } else {
                    $review = Review::create([
                        'user_id' => $user->getKey(),
                        'product_id' => $orderDetail->detailed_product->product_id,
                        'order_id' => $orderDetail->order_id,
                        'sku' => $orderDetail->sku,
                        'order_detail_id' => $orderDetailKey,
                        'rating' => $data['rating'],
                        'content' => $data['content'],
                        'status' => Review::STATUS_APPROVED,
                        'approved_at' => $approvedAt,
                    ]);
                }

                foreach ($request->file('images', []) as $index => $file) {
                    $extension = $file->extension() ?: $file->guessExtension() ?: 'jpg';
                    $fileName = Str::uuid() . '.' . strtolower($extension);
                    $path = $file->storeAs('review-images/' . $review->id, $fileName, 'public');
                    $storedPaths[] = $path;

                    ReviewImage::create([
                        'review_id' => $review->id,
                        'image_path' => $path,
                        'sort_order' => $index,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        return back()->with('success', 'Your review was submitted and published.');
    }
}
