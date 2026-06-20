<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisualFeedbackActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function feedback()
    {
        return $this->belongsTo(VisualFeedback::class, 'feedback_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
