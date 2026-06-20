<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisualFeedback extends Model
{
    use HasFactory;

    protected $table = 'visual_feedbacks';

    public const TYPES = [
        'Bug',
        'UI/UX',
        'Content',
        'Product information',
        'Wrong image',
        'Feature request',
        'Performance',
        'Accessibility',
        'Other',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    public const STATUSES = [
        'Open',
        'In Progress',
        'Need More Information',
        'Resolved',
        'Rejected',
        'Closed',
    ];

    protected $fillable = [
        'user_id',
        'assigned_to',
        'parent_id',
        'type',
        'priority',
        'status',
        'title',
        'description',
        'expected_result',
        'admin_note',
        'page_url',
        'page_path',
        'page_title',
        'route_name',
        'element_tag',
        'element_text',
        'element_id',
        'element_classes',
        'css_selector',
        'xpath',
        'element_attributes',
        'element_rect',
        'scroll_position',
        'viewport',
        'device_type',
        'browser_info',
        'screenshot_path',
        'created_by_role',
        'resolved_at',
    ];

    protected $casts = [
        'element_attributes' => 'array',
        'element_rect' => 'array',
        'scroll_position' => 'array',
        'viewport' => 'array',
        'browser_info' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function comments()
    {
        return $this->hasMany(VisualFeedbackComment::class, 'feedback_id')->latest();
    }

    public function publicComments()
    {
        return $this->comments()->where('is_internal', false);
    }

    public function attachments()
    {
        return $this->hasMany(VisualFeedbackAttachment::class, 'feedback_id');
    }

    public function activities()
    {
        return $this->hasMany(VisualFeedbackActivity::class, 'feedback_id')->latest();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->getKey();
    }

    public function isResolvedStatus(): bool
    {
        return in_array($this->status, ['Resolved', 'Closed'], true);
    }
}
