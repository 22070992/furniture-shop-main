@extends('layouts.admin')

@section('content')
    <div class="page-header d-print-none" data-feedback-id="admin.catalog-quality.run.header"
        data-catalog-quality-run-id="{{ $run->id }}">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Catalog Quality Run</div>
                    <h2 class="page-title">Run #{{ $run->id }}</h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <a href="{{ route('admin.catalog-quality.index') }}" class="btn">Back to dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($run->error_message)
                <div class="alert alert-warning">
                    <strong>AI scan warning:</strong>
                    <pre class="mb-0 mt-2 text-reset">{{ $run->error_message }}</pre>
                </div>
            @endif

            <div class="row row-cards mb-3">
                <div class="col-md-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Status</div>
                            <div class="h2">{{ ucfirst($run->status) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Scan type</div>
                            <div class="h2">{{ ucfirst($run->scan_type) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Products</div>
                            <div class="h2">{{ $run->total_products }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Issues</div>
                            <div class="h2 text-primary">{{ $run->total_issues }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-3">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Run details</h3>
                            <dl class="row">
                                <dt class="col-4">Started by</dt>
                                <dd class="col-8">{{ $run->starter?->full_name() ?? 'Unknown' }}</dd>
                                <dt class="col-4">Started</dt>
                                <dd class="col-8">{{ $run->started_at?->format('d/m/Y H:i:s') }}</dd>
                                <dt class="col-4">Completed</dt>
                                <dd class="col-8">{{ $run->completed_at?->format('d/m/Y H:i:s') ?? 'Not completed' }}</dd>
                                <dt class="col-4">AI provider</dt>
                                <dd class="col-8">
                                    Local AdminAiService text assistance
                                    <div class="text-muted small">Vision analysis is unavailable in the current provider.</div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <form class="card h-100" method="GET">
                        <div class="card-body">
                            <h3 class="card-title">Filter this run</h3>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Severity</label>
                                    <select name="severity" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($severities as $severity)
                                            <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Source</label>
                                    <select name="source" class="form-select">
                                        <option value="">All</option>
                                        @foreach ($sources as $source)
                                            <option value="{{ $source }}" @selected(request('source') === $source)>{{ ucfirst($source) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Search</label>
                                    <input class="form-control" name="q" value="{{ request('q') }}"
                                        placeholder="Product, issue, suggestion">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @include('admin.catalog_quality._issues_table', ['issues' => $issues])
        </div>
    </div>
@endsection
