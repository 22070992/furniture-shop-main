@extends('layouts.admin')

@section('content')
    <div class="page-header d-print-none" data-feedback-id="admin.ai-combo.header" data-component="AdminAiComboSettings">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <div class="page-pretitle">Furemir AI</div>
                    <h2 class="page-title">Interior Combo Recommendation</h2>
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
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('admin.ai-combo.update') }}" class="card">
                        @csrf
                        @method('PATCH')
                        <div class="card-body">
                            <h3 class="card-title">Recommendation settings</h3>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enabled" value="1"
                                            @checked($resolved['enabled'] ?? true)>
                                        <span class="form-check-label">Enable AI-assisted combo</span>
                                    </label>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">AI model label</label>
                                    <input type="text" name="model" class="form-control"
                                        value="{{ old('model', $resolved['model'] ?? 'furemir-rule-assisted') }}">
                                    <div class="form-hint">This is a display/config label only. API keys are never shown here.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Max products</label>
                                    <input type="number" min="3" max="5" name="max_products" class="form-control"
                                        value="{{ old('max_products', $resolved['max_products'] ?? 5) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Candidate limit</label>
                                    <input type="number" min="8" max="60" name="candidate_limit" class="form-control"
                                        value="{{ old('candidate_limit', $resolved['candidate_limit'] ?? 24) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Budget overage %</label>
                                    <input type="number" min="0" max="50" step="0.1"
                                        name="budget_overage_percent" class="form-control"
                                        value="{{ old('budget_overage_percent', $resolved['budget_overage_percent'] ?? 10) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Room/category mapping JSON</label>
                                    <textarea name="room_category_mapping" rows="12" class="form-control font-monospace">{{ old('room_category_mapping', json_encode($resolved['room_category_mapping'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea>
                                    <div class="form-hint">Use category names, not product IDs. The recommender will choose real active products dynamically.</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save settings</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title">Safety rules</h3>
                            <ul class="text-muted mb-0">
                                <li>AI can only select product IDs and SKUs from backend candidates.</li>
                                <li>Backend recalculates current prices and stock after every suggestion.</li>
                                <li>Inactive products, deleted SKUs and out-of-stock SKUs are removed.</li>
                                <li>Combo add-to-cart ignores any price sent from JavaScript.</li>
                                <li>If AI fails, rule-based recommendations still work.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-body">
                            <h3 class="card-title">Current resolved config</h3>
                            <dl class="row">
                                <dt class="col-5">Enabled</dt>
                                <dd class="col-7">{{ ($resolved['enabled'] ?? true) ? 'Yes' : 'No' }}</dd>
                                <dt class="col-5">Model</dt>
                                <dd class="col-7">{{ $resolved['model'] ?? 'furemir-rule-assisted' }}</dd>
                                <dt class="col-5">Max products</dt>
                                <dd class="col-7">{{ $resolved['max_products'] ?? 5 }}</dd>
                                <dt class="col-5">Candidate limit</dt>
                                <dd class="col-7">{{ $resolved['candidate_limit'] ?? 24 }}</dd>
                                <dt class="col-5">Budget buffer</dt>
                                <dd class="col-7">{{ $resolved['budget_overage_percent'] ?? 10 }}%</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
