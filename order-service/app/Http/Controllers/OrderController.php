<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Jobs\UpdateProductStock;

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

            $userResponse = Http::get('http://user-service:8000/api/users/' . $request->user_id);

            if (!$userResponse->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            $userData = $userResponse->json()['data'] ?? $userResponse->json();

            $productResponse = Http::get('http://product-service:8000/api/products/' . $request->product_id);

            if (!$productResponse->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Product tidak ditemukan'
                ], 404);
            }

            $productData = $productResponse->json()['data'] ?? $productResponse->json();

            if (!isset($productData['price_per_kg'])) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Harga tidak ditemukan'
                ], 500);
            }

            $total = $productData['price_per_kg'] * $request->qty;

            $order = Order::create([
                'user_id' => $request->user_id,
                'product_id' => $request->product_id,
                'qty' => $request->qty,
                'total_price' => $total
            ]);
            UpdateProductStock::dispatch([
                'product_id' => $request->product_id,
                'quantity' => $request->qty
            ])->onQueue('product-stock-update');

            return response()->json([
                'status' => 'success',
                'message' => 'Order berhasil dibuat',
                'data' => [
                    'order_id' => $order->id,
                    'user_name' => $userData['name'] ?? 'unknown',
                    'product_name' => $productData['name'] ?? 'unknown',
                    'qty' => $order->qty,
                    'total_price' => $total
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $orders = Order::all();

        $result = [];

        foreach ($orders as $order) {

            $user = Http::get("http://user-service:8000/api/users/" . $order->user_id)->json();
            $product = Http::get("http://product-service:8000/api/products/" . $order->product_id)->json();

            $userData = $user['data'] ?? $user;
            $productData = $product['data'] ?? $product;

            $result[] = [
                'order_id' => $order->id,
                'user_name' => $userData['name'] ?? 'unknown',
                'product_name' => $productData['name'] ?? 'unknown',
                'qty' => $order->qty,
                'total_price' => $order->total_price
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    public function show($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        $user = Http::get("http://user-service:8000/api/users/" . $order->user_id)->json();
        $product = Http::get("http://product-service:8000/api/products/" . $order->product_id)->json();

        $userData = $user['data'] ?? $user;
        $productData = $product['data'] ?? $product;

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->id,
                'user_name' => $userData['name'] ?? 'unknown',
                'product_name' => $productData['name'] ?? 'unknown',
                'qty' => $order->qty,
                'total_price' => $order->total_price
            ]
        ]);
    }
}
