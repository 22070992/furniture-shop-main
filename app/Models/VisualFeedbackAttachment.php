<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisualFeedbackAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_id',
        'user_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
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
