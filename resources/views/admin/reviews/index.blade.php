@extends('layouts.admin')

@section('content')
    <div class="page-header d-print-none" data-feedback-id="admin.reviews.index.header">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Catalog quality</div>
                    <h2 class="page-title">Verified Product Reviews</h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <a href="{{ route('admin.reviews.index', ['status' => \App\Models\Review::STATUS_PENDING]) }}"
                        class="btn btn-primary">Pending reviews</a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Total</div>
                            <div class="h2">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Pending</div>
                            <div class="h2 text-warning">{{ $stats['pending'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Approved</div>
                            <div class="h2 text-success">{{ $stats['approved'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Rejected</div>
                            <div class="h2 text-danger">{{ $stats['rejected'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <form class="card mb-3" method="GET" data-feedback-id="admin.reviews.filters">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">Search</label>
                            <input class="form-control" name="q" value="{{ request('q') }}"
                                placeholder="Product, user, SKU, content">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Rating</label>
                            <select class="form-select" name="rating">
                                <option value="">All</option>
                                @for ($rating = 5; $rating >= 1; $rating--)
                                    <option value="{{ $rating }}" @selected((string) request('rating') === (string) $rating)>
                                        {{ $rating }} stars
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">Filter</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Review</th>
                                <th>User</th>
                                <th>Product</th>
                                <th>Order detail</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reviews as $review)
                                <tr data-feedback-id="admin.reviews.row" data-review-id="{{ $review->id }}">
                                    <td>#{{ $review->id }}</td>
                                    <td>
                                        @if ($review->images->first())
                                            <span class="avatar"
                                                style="background-image: url({{ $review->images->first()->url() }})"></span>
                                        @else
                                            <span class="text-muted">None</span>
                                        @endif
                                    </td>
                                    <td class="text-break">
                                        <strong>{{ $review->rating }}/5</strong>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($review->content, 90) }}</div>
                                    </td>
                                    <td>
                                        {{ $review->user?->full_name() ?? 'Unknown' }}
                                        <div class="text-muted small">{{ $review->user?->email }}</div>
                                    </td>
                                    <td class="text-break">
                                        <a href="/products/{{ $review->product_id }}" target="_blank">
                                            {{ $review->product?->name ?? 'Product #' . $review->product_id }}
                                        </a>
                                    </td>
                                    <td>
                                        Order #{{ $review->order_id }}
                                        <div class="text-muted small">SKU {{ $review->sku }}</div>
                                    </td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'bg-yellow-lt' => $review->status === \App\Models\Review::STATUS_PENDING,
                                            'bg-green-lt' => $review->status === \App\Models\Review::STATUS_APPROVED,
                                            'bg-red-lt' => $review->status === \App\Models\Review::STATUS_REJECTED,
                                        ])>{{ ucfirst($review->status) }}</span>
                                    </td>
                                    <td>{{ $review->created_at->format('d/m/Y H:i') }}</td>
                                    <td><a class="btn btn-sm" href="{{ route('admin.reviews.show', $review) }}">View</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No reviews found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    {{ $reviews->links('common.pagination') }}
                </div>
            </div>
        </div>
    </div>
@endsection
