<div class="admin-ai-summary">
    <div class="admin-ai-headline">{{ $summary['headline'] }}</div>
    <p class="text-muted mb-3">{{ $summary['overview'] }}</p>

    <div class="row g-3 mb-3">
        @foreach ($summary['metrics'] as $metric)
            <div class="col-sm-6 col-lg-3">
                <div class="admin-ai-metric">
                    <span>{{ $metric['label'] }}</span>
                    <strong>{{ $metric['value'] }}</strong>
                    <em class="text-{{ $metric['tone'] }}">{{ $metric['status'] }}</em>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="admin-ai-list">
                <div class="subheader mb-2">Insights</div>
                @foreach ($summary['insights'] as $insight)
                    <div class="admin-ai-list-item">{{ $insight }}</div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-6">
            <div class="admin-ai-list">
                <div class="subheader mb-2">Recommended actions</div>
                @foreach ($summary['actions'] as $action)
                    <div class="admin-ai-list-item">{{ $action }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>
