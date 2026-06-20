<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogQualityRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SCAN_RULE = 'rule';
    public const SCAN_AI = 'ai';

    protected $fillable = [
        'started_by',
        'status',
        'scan_type',
        'total_products',
        'total_issues',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function starter()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function issues()
    {
        return $this->hasMany(CatalogQualityIssue::class, 'run_id');
    }
}
