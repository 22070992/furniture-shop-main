<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteVisit extends Model
{
    use HasFactory;

    protected $table = 'site_visits';
    protected $primaryKey = 'site_visit_id';

    protected $fillable = [
        'session_id',
        'user_id',
        'converted_order_id',
        'url',
        'referrer',
        'source',
        'device',
        'browser',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convertedOrder()
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
