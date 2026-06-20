<?php

namespace App\Http\Controllers;

use App\Models\AiComboSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminAiComboController extends Controller
{
    public function index()
    {
        return view('admin.ai_combo.index', [
            'setting' => AiComboSetting::current(),
            'resolved' => AiComboSetting::resolved(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'model' => ['required', 'string', 'max:120'],
            'max_products' => ['required', 'integer', 'min:3', 'max:5'],
            'candidate_limit' => ['required', 'integer', 'min:8', 'max:60'],
            'budget_overage_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'room_category_mapping' => ['required', 'string', 'max:8000'],
        ]);

        $mapping = json_decode($data['room_category_mapping'], true);
        if (!is_array($mapping)) {
            throw ValidationException::withMessages([
                'room_category_mapping' => 'Room/category mapping must be valid JSON.',
            ]);
        }

        foreach ($mapping as $room => $categories) {
            if (!is_string($room) || !is_array($categories)) {
                throw ValidationException::withMessages([
                    'room_category_mapping' => 'Each room must map to an array of category names.',
                ]);
            }
        }

        $setting = AiComboSetting::current() ?: new AiComboSetting();
        $setting->fill([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'model' => $data['model'],
            'max_products' => (int) $data['max_products'],
            'candidate_limit' => (int) $data['candidate_limit'],
            'budget_overage_percent' => (float) $data['budget_overage_percent'],
            'room_category_mapping' => $mapping,
            'updated_by' => $request->user()?->getKey(),
        ]);
        $setting->save();

        return redirect()
            ->route('admin.ai-combo.index')
            ->with('success', 'AI combo settings updated.');
    }
}
