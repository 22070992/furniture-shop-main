<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Issue</th>
                    <th>Product / SKU</th>
                    <th>Severity</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Suggestion</th>
                    <th>Run</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($issues as $issue)
                    <tr data-feedback-id="admin.catalog-quality.issue-row" data-issue-id="{{ $issue->id }}">
                        <td>#{{ $issue->id }}</td>
                        <td class="text-break">
                            <strong>{{ $issue->issue_code }}</strong>
                            @if ($issue->field_name)
                                <div class="text-muted small">Field: {{ $issue->field_name }}</div>
                            @endif
                            @if ($issue->current_value !== null && $issue->current_value !== '')
                                <div class="text-muted small">{{ \Illuminate\Support\Str::limit($issue->current_value, 90) }}</div>
                            @endif
                        </td>
                        <td class="text-break">
                            @if ($issue->product)
                                <a href="{{ route('products.update_ui', $issue->product_id) }}">
                                    {{ $issue->product->name }}
                                </a>
                                <div class="text-muted small">#{{ $issue->product_id }}</div>
                            @else
                                <span class="text-muted">Run-level issue</span>
                            @endif
                            @if ($issue->sku)
                                <div class="text-muted small">SKU {{ $issue->sku }}</div>
                            @endif
                        </td>
                        <td>
                            <span @class([
                                'badge',
                                'bg-red-lt' => $issue->severity === 'critical',
                                'bg-orange-lt' => $issue->severity === 'high',
                                'bg-yellow-lt' => $issue->severity === 'medium',
                                'bg-blue-lt' => in_array($issue->severity, ['low', 'info'], true),
                            ])>{{ ucfirst($issue->severity) }}</span>
                        </td>
                        <td>{{ ucfirst($issue->source) }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'bg-blue-lt' => $issue->status === \App\Models\CatalogQualityIssue::STATUS_OPEN,
                                'bg-secondary-lt' => $issue->status === \App\Models\CatalogQualityIssue::STATUS_IGNORED,
                                'bg-green-lt' => $issue->status === \App\Models\CatalogQualityIssue::STATUS_RESOLVED,
                            ])>{{ ucfirst($issue->status) }}</span>
                        </td>
                        <td class="text-break" style="max-width: 280px;">
                            @if ($issue->suggestion)
                                {{ \Illuminate\Support\Str::limit($issue->suggestion, 140) }}
                            @else
                                <span class="text-muted">No suggestion</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.catalog-quality.runs.show', $issue->run_id) }}">#{{ $issue->run_id }}</a>
                            <div class="text-muted small">{{ $issue->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @if ($issue->status !== \App\Models\CatalogQualityIssue::STATUS_RESOLVED)
                                    <form method="POST" action="{{ route('admin.catalog-quality.issues.update', $issue) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="resolved">
                                        <button class="btn btn-sm" type="submit">Resolved</button>
                                    </form>
                                @endif
                                @if ($issue->status !== \App\Models\CatalogQualityIssue::STATUS_IGNORED)
                                    <form method="POST" action="{{ route('admin.catalog-quality.issues.update', $issue) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="ignored">
                                        <button class="btn btn-sm" type="submit">Ignore</button>
                                    </form>
                                @endif
                                @if ($issue->canApplySuggestion())
                                    <form method="POST" action="{{ route('admin.catalog-quality.issues.apply', $issue) }}"
                                        onsubmit="return confirm('Apply this AI suggestion to {{ $issue->field_name }}?');">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" type="submit">Apply</button>
                                    </form>
                                @endif
                                @if ($issue->product)
                                    <form method="POST" action="{{ route('admin.catalog-quality.products.rescan', $issue->product) }}">
                                        @csrf
                                        <input type="hidden" name="scan_type" value="rule">
                                        <button class="btn btn-sm" type="submit">Rescan</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">No catalog quality issues found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-end">
        {{ $issues->links('common.pagination') }}
    </div>
</div>
