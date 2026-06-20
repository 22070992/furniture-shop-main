<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogQualityIssue extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_RESOLVED = 'resolved';

    public const SOURCE_RULE = 'rule';
    public const SOURCE_AI = 'ai';

    protected $fillable = [
        'run_id',
        'product_id',
        'sku',
        'issue_code',
        'severity',
        'source',
        'field_name',
        'current_value',
        'suggestion',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function run()
    {
        return $this->belongsTo(CatalogQualityRun::class, 'run_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productDetail()
    {
        return $this->belongsTo(ProductDetail::class, 'sku', 'sku');
    }

    public function canApplySuggestion(): bool
    {
        return $this->source === self::SOURCE_AI
            && $this->status === self::STATUS_OPEN
            && in_array($this->field_name, config('shop.catalog_quality.allowed_apply_fields', []), true)
            && filled($this->suggestion)
            && $this->product_id;
    }
}
