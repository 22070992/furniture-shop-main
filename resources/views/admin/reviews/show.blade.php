@extends('layouts.admin')

@section('content')
    <div class="page-header d-print-none" data-feedback-id="admin.reviews.show.header">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Product Review</div>
                    <h2 class="page-title">Review #{{ $review->id }}</h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a href="{{ route('admin.reviews.index') }}" class="btn">Back</a>
                        <a href="/products/{{ $review->product_id }}" target="_blank" class="btn btn-primary">
                            Open product
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="row row-cards">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Review content</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <strong>{{ $review->rating }}/5 stars</strong>
                                    <span class="badge bg-green-lt ms-2">Verified purchase</span>
                                </div>
                                <span @class([
                                    'badge',
                                    'bg-yellow-lt' => $review->status === \App\Models\Review::STATUS_PENDING,
                                    'bg-green-lt' => $review->status === \App\Models\Review::STATUS_APPROVED,
                                    'bg-red-lt' => $review->status === \App\Models\Review::STATUS_REJECTED,
                                ])>{{ ucfirst($review->status) }}</span>
                            </div>
                            <p class="mb-0">{{ $review->content }}</p>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Review images</h3>
                        </div>
                        <div class="card-body">
                            @if ($review->images->isNotEmpty())
                                <div class="row g-2">
                                    @foreach ($review->images as $image)
                                        <div class="col-6 col-md-3">
                                            <a href="{{ $image->url() }}" target="_blank">
                                                <img src="{{ $image->url() }}" class="img-fluid rounded border"
                                                    alt="Review image">
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted mb-0">No images attached.</p>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Linked order detail</h3>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-md-3">Order</dt>
                                <dd class="col-md-9">
                                    <a href="{{ route('orders.details', $review->order_id) }}">#{{ $review->order_id }}</a>
                                    <span class="text-muted">({{ $review->order?->get_status() }})</span>
                                </dd>
                                <dt class="col-md-3">SKU</dt>
                                <dd class="col-md-9">{{ $review->sku }}</dd>
                                <dt class="col-md-3">Product</dt>
                                <dd class="col-md-9">{{ $review->product?->name ?? 'Product #' . $review->product_id }}</dd>
                                <dt class="col-md-3">Variant</dt>
                                <dd class="col-md-9">
                                    {{ $review->productDetail?->name }}
                                    @if ($review->productDetail?->color)
                                        <span class="text-muted">- {{ $review->productDetail->color->name }}</span>
                                    @endif
                                </dd>
                                <dt class="col-md-3">Submitted by</dt>
                                <dd class="col-md-9">
                                    {{ $review->user?->full_name() ?? 'Unknown' }}
                                    <span class="text-muted">#{{ $review->user_id }}</span>
                                </dd>
                                <dt class="col-md-3">Approved by</dt>
                                <dd class="col-md-9">
                                    @if ($review->approver)
                                        {{ $review->approver->full_name() }}
                                        at {{ $review->approved_at?->format('d/m/Y H:i') }}
                                    @else
                                        Not approved yet
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="card">
                        @csrf
                        @method('PATCH')
                        <div class="card-header">
                            <h3 class="card-title">Moderation</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}" @selected($review->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin note</label>
                                <textarea name="admin_note" class="form-control" rows="6"
                                    placeholder="Reason for rejection or internal moderation note">{{ $review->admin_note }}</textarea>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Save moderation</button>
                        </div>
                    </form>

                    <div class="card mt-3">
                        <div class="card-body d-flex gap-2">
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="flex-fill">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Models\Review::STATUS_APPROVED }}">
                                <input type="hidden" name="admin_note" value="{{ $review->admin_note }}">
                                <button class="btn btn-success w-100" type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="flex-fill">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ \App\Models\Review::STATUS_REJECTED }}">
                                <input type="hidden" name="admin_note" value="{{ $review->admin_note }}">
                                <button class="btn btn-danger w-100" type="submit">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
