<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\PaymentResource;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function index()
{
    $payments = Payment::all();
    $result = [];

    foreach ($payments as $payment) {

        $orderResponse = Http::get('http://127.0.0.1:8003/api/orders/' . $payment->order_id);

        if ($orderResponse->successful()) {
            $orderData = $orderResponse->json();
            $order = $orderData['data'] ?? $orderData;
        } else {
            $order = null;
        }

        $result[] = [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'user_name' => $order['user_name'] ?? 'unknown',
            'product_name' => $order['product_name'] ?? 'unknown',
            'amount' => $payment->amount,
            'method' => $payment->method,
            'status' => $payment->status
        ];
    }
    return response()->json([
        'status' => 'success',
        'message' => 'List semua payment',
        'data' => $result
    ]);
}

    public function show($id)
{
    $payment = Payment::find($id);

    if (!$payment) {
        return response()->json([
            'status' => 'failed',
            'message' => 'Data Payment tidak ditemukan'
        ], 404);
    }

    $orderResponse = Http::get('http://127.0.0.1:8003/api/orders/' . $payment->order_id);

    if ($orderResponse->successful()) {
        $orderData = $orderResponse->json();
        $order = $orderData['data'] ?? $orderData;
    } else {
        $order = null;
    }

    return response()->json([
        'status' => 'success',
        'data' => [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'user_name' => $order['user_name'] ?? 'unknown',
            'product_name' => $order['product_name'] ?? 'unknown',
            'amount' => $payment->amount,
            'method' => $payment->method,
            'status' => $payment->status
        ]
    ]);
}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'method' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $orderResponse = Http::get('http://127.0.0.1:8003/api/orders/' . $request->order_id);

            if (!$orderResponse->successful()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order tidak ditemukan / service mati'
                ], 404);
            }

            $orderData = $orderResponse->json();

            if (!isset($orderData['data'])) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Format response OrderService salah',
                    'debug' => $orderData
                ], 500);
            }

            $order = $orderData['data'];

            if (!isset($order['total_price'])) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'total_price tidak ditemukan',
                    'debug' => $order
                ], 500);
            }

            $existing = Payment::where('order_id', $request->order_id)->first();

            if ($existing) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order sudah dibayar'
                ], 400);
            }

            $payment = Payment::create([
                'order_id' => $request->order_id,
                'amount' => $order['total_price'],
                'method' => $request->method,
                'status' => 'paid'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'user_name' => $order['user_name'],
                    'product_name' => $order['product_name'],
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'status' => $payment->status
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Data Payment tidak ditemukan'
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data Payment berhasil dihapus'
        ]);
    }
}
