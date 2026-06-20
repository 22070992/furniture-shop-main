<?php

namespace App\Http\Controllers;

use App\Services\AiInteriorComboService;
use Illuminate\Http\Request;

class AiInteriorComboController extends Controller
{
    public function recommend(Request $request, AiInteriorComboService $service)
    {
        $data = $request->validate([
            'room' => ['nullable', 'string', 'max:80'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:500000000'],
            'style' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:80'],
            'dimensions' => ['nullable', 'string', 'max:120'],
            'prompt' => ['nullable', 'string', 'max:1000'],
            'product_id' => ['nullable', 'integer', 'exists:products,product_id'],
        ]);

        return response()->json($service->recommend($data));
    }

    public function cartItems(Request $request, AiInteriorComboService $service)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:5'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'items.*.price' => ['nullable'],
            'items.*.unit_price' => ['nullable'],
            'items.*.total' => ['nullable'],
        ]);

        return response()->json($service->cartItems($data['items']));
    }
}
