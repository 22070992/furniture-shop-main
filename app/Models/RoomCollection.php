<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_key',
        'slug',
        'title',
        'description',
        'hero_image',
        'category_keywords',
        'featured_category_ids',
        'meta_title',
        'meta_description',
        'is_active',
    ];

    protected $casts = [
        'category_keywords' => 'array',
        'featured_category_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function publicUrl(): string
    {
        return route('collections.show', $this->slug);
    }
}
