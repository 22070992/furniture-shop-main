@extends('layouts.admin')

@section('content')
    <div class="page-header d-print-none" data-feedback-id="admin.feedback.index.header" data-component="AdminFeedbackIndex">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Quality</div>
                    <h2 class="page-title">Tất cả Visual Feedback</h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <div class="btn-list">
                        <a href="{{ route('admin.feedback.index') }}" class="btn">Xem tất cả</a>
                        <a href="{{ route('admin.feedback.index', ['status' => 'Open']) }}" class="btn btn-primary">
                            Feedback đang mở
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

            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body"><div class="subheader">Total</div><div class="h2">{{ $stats['total'] }}</div></div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body"><div class="subheader">Open</div><div class="h2">{{ $stats['open'] }}</div></div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body"><div class="subheader">In Progress</div><div class="h2">{{ $stats['in_progress'] }}</div></div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body"><div class="subheader">Critical</div><div class="h2 text-danger">{{ $stats['critical'] }}</div></div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body"><div class="subheader">Resolved</div><div class="h2 text-success">{{ $stats['resolved'] }}</div></div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-3">
                <div class="col-lg-8">
                    <form class="card" method="GET" data-feedback-id="admin.feedback.filters">
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input name="q" class="form-control" value="{{ request('q') }}"
                                        placeholder="Title, description, selector">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($priorities as $priority)
                                            <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($types as $type)
                                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Admin</label>
                                    <select name="assigned_to" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->user_id }}" @selected((string) request('assigned_to') === (string) $admin->user_id)>
                                                {{ $admin->full_name() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Người gửi</label>
                                    <select name="user_id" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($submitters as $submitter)
                                            <option value="{{ $submitter->user_id }}" @selected((string) request('user_id') === (string) $submitter->user_id)>
                                                {{ $submitter->full_name() }} - #{{ $submitter->user_id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Page path</label>
                                    <input name="page_path" class="form-control" value="{{ request('page_path') }}" placeholder="/shop">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_screenshot" value="1"
                                            @checked(request()->boolean('has_screenshot'))>
                                        <span class="form-check-label">Only with screenshot</span>
                                    </label>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <a href="{{ route('admin.feedback.index') }}" class="btn w-100">Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title">Top pages</h3>
                            @foreach ($pageStats as $page)
                                <div class="d-flex justify-content-between border-bottom py-1">
                                    <span class="text-truncate">{{ $page->page_path ?: 'Unknown' }}</span>
                                    <strong>{{ $page->total }}</strong>
                                </div>
                            @endforeach
                            <h3 class="card-title mt-3">By type</h3>
                            @foreach ($typeStats as $type)
                                <div class="d-flex justify-content-between border-bottom py-1">
                                    <span>{{ $type->type }}</span>
                                    <strong>{{ $type->total }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Screenshot</th>
                                <th>Title</th>
                                <th>User</th>
                                <th>Page</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assignee</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($feedbacks as $feedback)
                                <tr data-feedback-id="admin.feedback.row" data-feedback-record-id="{{ $feedback->id }}">
                                    <td>#{{ $feedback->id }}</td>
                                    <td>
                                        @if ($feedback->screenshot_path)
                                            <img src="{{ route('feedback.screenshot', $feedback) }}" width="72" height="48"
                                                class="rounded border object-cover" style="object-fit: cover;" alt="Screenshot">
                                        @else
                                            <span class="text-muted">None</span>
                                        @endif
                                    </td>
                                    <td class="text-break">
                                        <a href="{{ route('admin.feedback.show', $feedback) }}">{{ $feedback->title }}</a>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($feedback->element_text, 70) }}</div>
                                    </td>
                                    <td>
                                        {{ $feedback->user?->full_name() ?? 'Unknown' }}
                                        <div class="text-muted small">{{ $feedback->created_by_role }}</div>
                                    </td>
                                    <td class="text-break">{{ $feedback->page_path }}</td>
                                    <td>{{ $feedback->type }}</td>
                                    <td><span class="badge bg-orange-lt">{{ $feedback->priority }}</span></td>
                                    <td><span class="badge bg-blue-lt">{{ $feedback->status }}</span></td>
                                    <td>{{ $feedback->assignee?->full_name() ?? 'Unassigned' }}</td>
                                    <td>{{ $feedback->created_at->format('d/m/Y H:i') }}</td>
                                    <td><a class="btn btn-sm" href="{{ route('admin.feedback.show', $feedback) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted">No feedback found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    {{ $feedbacks->links('common.pagination') }}
                </div>
            </div>
        </div>
    </div>
@endsection
