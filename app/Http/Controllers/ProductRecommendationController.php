<?php

namespace App\Http\Controllers;

use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;

class ProductRecommendationController extends Controller
{
    public function comboCartItems(Request $request, ProductRecommendationService $recommendations)
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:6'],
            'product_ids.*' => ['integer'],
        ]);

        return response()->json($recommendations->comboCartItems($data['product_ids']));
    }
}
