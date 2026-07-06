@extends('layouts.app')

@section('content')
    <main class="container py-5" data-feedback-id="feedback.mine.page" data-component="MyFeedbackPage">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="mb-1">Phản hồi của tôi</h1>
                <p class="text-muted mb-0">Theo dõi các lỗi hoặc đề xuất bạn đã gửi cho Furemir.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="GET" class="row g-2 align-items-end mb-4" data-feedback-id="feedback.mine.filters">
            <div class="col-md-4">
                <label class="form-label">Tìm kiếm</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                    placeholder="Tiêu đề, mô tả, element">
            </div>
            <div class="col-md-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-control">
                    <option value="">Tất cả</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Lọc</button>
            </div>
        </form>

        <div class="row g-3">
            @forelse ($feedbacks as $feedback)
                <div class="col-lg-6">
                    <a class="card h-100 text-reset text-decoration-none" href="{{ route('feedback.show', $feedback) }}"
                        data-feedback-id="feedback.mine.card" data-feedback-record-id="{{ $feedback->id }}">
                        <div class="row g-0 h-100">
                            <div class="col-4">
                                @if ($feedback->screenshot_path)
                                    <img src="{{ route('feedback.screenshot', $feedback) }}" class="w-100 h-100"
                                        style="object-fit: cover; min-height: 150px;" alt="Feedback screenshot">
                                @else
                                    <div class="bg-light h-100 d-flex align-items-center justify-content-center text-muted">
                                        No image
                                    </div>
                                @endif
                            </div>
                            <div class="col-8">
                                <div class="card-body">
                                    <div class="d-flex gap-2 flex-wrap mb-2">
                                        <span class="badge bg-primary">{{ $feedback->type }}</span>
                                        <span class="badge bg-info">{{ $feedback->status }}</span>
                                    </div>
                                    <h4 class="mb-2">#{{ $feedback->id }} {{ $feedback->title }}</h4>
                                    <p class="text-muted mb-2">{{ \Illuminate\Support\Str::limit($feedback->description, 120) }}</p>
                                    <small class="text-muted">{{ $feedback->page_path }} · {{ $feedback->created_at->format('d/m/Y H:i') }}</small>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">Bạn chưa gửi feedback nào. Bấm nút “Gửi phản hồi” ở góc dưới để bắt đầu.</div>
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $feedbacks->links() }}
        </div>
    </main>
@endsection
