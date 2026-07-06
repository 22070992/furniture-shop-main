@extends('layouts.app')

@section('content')
    <main class="container py-5" data-feedback-id="feedback.show.page" data-feedback-record-id="{{ $feedback->id }}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <a href="{{ route('feedback.mine') }}" class="text-muted">← Phản hồi của tôi</a>
                <h1 class="mb-1">#{{ $feedback->id }} {{ $feedback->title }}</h1>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-primary">{{ $feedback->type }}</span>
                    <span class="badge bg-info">{{ $feedback->status }}</span>
                </div>
            </div>
            <a href="{{ $feedback->page_url }}" target="_blank" rel="noreferrer" class="btn btn-outline-primary">Mở trang gốc</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="mb-0">Ảnh và vị trí lỗi</h3>
                    </div>
                    <div class="card-body">
                        @if ($feedback->screenshot_path)
                            <img src="{{ route('feedback.screenshot', $feedback) }}" class="img-fluid rounded border"
                                alt="Feedback screenshot">
                        @else
                            <div class="alert alert-warning">Feedback này chưa có screenshot.</div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Bình luận</h3>
                    </div>
                    <div class="card-body">
                        @forelse ($feedback->publicComments as $comment)
                            <div class="border-bottom pb-3 mb-3">
                                <strong>{{ $comment->user?->full_name() ?? 'User' }}</strong>
                                <span class="text-muted small">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                <p class="mb-0 mt-2">{{ $comment->comment }}</p>
                            </div>
                        @empty
                            <p class="text-muted">Chưa có bình luận công khai.</p>
                        @endforelse

                        <form method="POST" action="{{ route('feedback.comments.store', $feedback) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Bổ sung thông tin</label>
                                <textarea name="comment" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Đính kèm file</label>
                                <input type="file" name="attachment" class="form-control"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.doc,.docx">
                            </div>
                            <button type="submit" class="btn btn-primary">Gửi bình luận</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="mb-0">Chi tiết</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Trang</dt>
                            <dd class="col-sm-8 text-break">{{ $feedback->page_url }}</dd>
                            <dt class="col-sm-4">Element</dt>
                            <dd class="col-sm-8 text-break">{{ $feedback->css_selector }}</dd>
                            <dt class="col-sm-4">Text</dt>
                            <dd class="col-sm-8">{{ $feedback->element_text ?: 'Không có' }}</dd>
                            <dt class="col-sm-4">Ngày gửi</dt>
                            <dd class="col-sm-8">{{ $feedback->created_at->format('d/m/Y H:i') }}</dd>
                            <dt class="col-sm-4">Admin note</dt>
                            <dd class="col-sm-8">{{ $feedback->admin_note ?: 'Chưa có' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="mb-0">Nội dung</h3>
                    </div>
                    <div class="card-body">
                        <h4>Mô tả</h4>
                        <p>{{ $feedback->description }}</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">File đính kèm</h3>
                    </div>
                    <div class="card-body">
                        @forelse ($feedback->attachments as $attachment)
                            <a class="d-block mb-2" href="{{ route('feedback.attachment', $attachment) }}">
                                {{ $attachment->file_name }}
                            </a>
                        @empty
                            <p class="text-muted mb-0">Không có file đính kèm.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
