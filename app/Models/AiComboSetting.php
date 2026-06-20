<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AiComboSetting extends Model
{
    protected $fillable = [
        'enabled',
        'model',
        'max_products',
        'candidate_limit',
        'budget_overage_percent',
        'room_category_mapping',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'max_products' => 'integer',
        'candidate_limit' => 'integer',
        'budget_overage_percent' => 'float',
        'room_category_mapping' => 'array',
    ];

    public static function current(): ?self
    {
        if (!Schema::hasTable('ai_combo_settings')) {
            return null;
        }

        return static::query()->first();
    }

    public static function resolved(): array
    {
        $defaults = config('shop.ai_interior_combo', []);
        $setting = static::current();

        if (!$setting) {
            return $defaults;
        }

        return array_merge($defaults, [
            'enabled' => (bool) $setting->enabled,
            'model' => $setting->model ?: ($defaults['model'] ?? 'furemir-rule-assisted'),
            'max_products' => (int) $setting->max_products,
            'candidate_limit' => (int) $setting->candidate_limit,
            'budget_overage_percent' => (float) $setting->budget_overage_percent,
            'room_category_mapping' => $setting->room_category_mapping ?: ($defaults['room_category_mapping'] ?? []),
        ]);
    }
}
