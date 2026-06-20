@extends('layouts.admin')

@section('content')
    <div class="page-header d-print-none" data-feedback-id="admin.catalog-quality.index.header"
        data-component="AdminCatalogQualityIndex">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Furemir AI</div>
                    <h2 class="page-title">Catalog Quality Checker</h2>
                </div>
                <div class="col-auto ms-auto d-print-none">
                    <a href="{{ route('admin.catalog-quality.index', ['status' => 'open']) }}" class="btn btn-primary">
                        Open issues
                    </a>
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

            <div class="row row-cards mb-3">
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Runs</div>
                            <div class="h2">{{ $stats['runs'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Issues</div>
                            <div class="h2">{{ $stats['issues'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Open</div>
                            <div class="h2 text-primary">{{ $stats['open'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Critical</div>
                            <div class="h2 text-danger">{{ $stats['critical'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="subheader">Resolved</div>
                            <div class="h2 text-success">{{ $stats['resolved'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cards mb-3">
                <div class="col-lg-7">
                    <form class="card" method="POST" action="{{ route('admin.catalog-quality.runs.store') }}"
                        data-feedback-id="admin.catalog-quality.scan-form">
                        @csrf
                        <div class="card-body">
                            <h3 class="card-title">Run catalog scan</h3>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label">Scan type</label>
                                    <select name="scan_type" class="form-select">
                                        <option value="rule">Rule scan</option>
                                        <option value="ai">AI-assisted scan</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Scope</label>
                                    <select name="scope" class="form-select">
                                        <option value="all">All catalog</option>
                                        <option value="category">Category</option>
                                        <option value="product">Single product</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all">All</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Limit</label>
                                    <input name="limit" type="number" min="1"
                                        max="{{ config('shop.catalog_quality.max_scan_products') }}" class="form-control"
                                        value="{{ config('shop.catalog_quality.max_scan_products') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">Any category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->category_id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Product</label>
                                    <select name="product_id" class="form-select">
                                        <option value="">Any product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->product_id }}">#{{ $product->product_id }} -
                                                {{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <button class="btn btn-primary" type="submit">Run scan</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="card-title">Latest runs</h3>
                            @forelse ($runs as $latestRun)
                                <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                    <div>
                                        <a href="{{ route('admin.catalog-quality.runs.show', $latestRun) }}">
                                            Run #{{ $latestRun->id }}
                                        </a>
                                        <div class="text-muted small">
                                            {{ ucfirst($latestRun->scan_type) }} by
                                            {{ $latestRun->starter?->full_name() ?? 'Unknown' }}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span @class([
                                            'badge',
                                            'bg-green-lt' => $latestRun->status === 'completed',
                                            'bg-yellow-lt' => $latestRun->status === 'running',
                                            'bg-red-lt' => $latestRun->status === 'failed',
                                        ])>{{ ucfirst($latestRun->status) }}</span>
                                        <div class="text-muted small">{{ $latestRun->total_issues }} issues</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">No scan runs yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <form class="card mb-3" method="GET" data-feedback-id="admin.catalog-quality.filters">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input name="q" class="form-control" value="{{ request('q') }}"
                                placeholder="Product, issue, suggestion">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select">
                                <option value="">All</option>
                                @foreach ($severities as $severity)
                                    <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Source</label>
                            <select name="source" class="form-select">
                                <option value="">All</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source }}" @selected(request('source') === $source)>{{ ucfirst($source) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Issue type</label>
                            <select name="issue_code" class="form-select">
                                <option value="">All</option>
                                @foreach ($issueCodes as $issueCode)
                                    <option value="{{ $issueCode }}" @selected(request('issue_code') === $issueCode)>{{ $issueCode }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select">
                                <option value="">All</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->product_id }}" @selected((string) request('product_id') === (string) $product->product_id)>
                                        #{{ $product->product_id }} - {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SKU</label>
                            <input name="sku" class="form-control" value="{{ request('sku') }}" placeholder="SKU">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" type="submit">Filter</button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a class="btn w-100" href="{{ route('admin.catalog-quality.index') }}">Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            @include('admin.catalog_quality._issues_table', ['issues' => $issues])
        </div>
    </div>
@endsection
