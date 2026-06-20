<?php

namespace App\Http\Controllers;

use App\Services\AdminAiService;
use Illuminate\Http\Request;

class AdminAiController extends Controller
{
    public function productContent(Request $request, AdminAiService $adminAi)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,category_id'],
            'brand_id' => ['nullable', 'exists:brands,brand_id'],
            'material' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
        ]);

        return response()->json($adminAi->generateProductContent($validated));
    }

    public function dashboardSummary(AdminAiService $adminAi)
    {
        return response()->json($adminAi->dashboardSummary());
    }
}
