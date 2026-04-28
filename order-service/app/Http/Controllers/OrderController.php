<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Http\Resources\OrderResource;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        try {

            $request->validate([
                'user_id' => 'required',
                'product_id' => 'required',
                'qty' => 'required|numeric|min:1',
            ]);

            $userResponse = Http::get('http://127.0.0.1:8001/api/users/' . $request->user_id);

            if (!$userResponse->successful()) {
                return response()->json([
                    'message' => 'UserService error',
                    'debug' => $userResponse->body()
                ], 404);
            }

            $productResponse = Http::get('http://127.0.0.1:8002/api/products/' . $request->product_id);

            if (!$productResponse->successful()) {
                return response()->json([
                    'message' => 'ProductService error',
                    'debug' => $productResponse->body()
                ], 404);
            }

            $productData = $productResponse->json();
            $dataProduct = $productData['data'] ?? $productData;

            if (!isset($dataProduct['price_per_kg'])) {
                return response()->json([
                    'message' => 'price_per_kg tidak ditemukan',
                    'debug' => $dataProduct
                ], 500);
            }

            $total = $dataProduct['price_per_kg'] * $request->qty;

            $order = Order::create([
                'user_id' => $request->user_id,
                'product_id' => $request->product_id,
                'qty' => $request->qty,
                'total_price' => $total
            ]);

            return response()->json([
                'message' => 'Order sukses',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index()
    {
        $orders = Order::all();
        return OrderResource::collection($orders);
    }
}
