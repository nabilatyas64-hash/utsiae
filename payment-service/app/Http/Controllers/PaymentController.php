<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Jobs\ProcessPaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::all();
        $result = [];

        foreach ($payments as $payment) {
            $orderResponse = Http::get('http://order-service:8000/api/orders/' . $payment->order_id);
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
            Log::info("Processing payment for order: " . $request->order_id);

            // 1. Ambil data dari Order Service
            $orderResponse = Http::get('http://order-service:8000/api/orders/' . $request->order_id);

            if (!$orderResponse->successful()) {
                Log::error("Order service unreachable or order not found: " . $request->order_id);
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Order tidak ditemukan / service mati'
                ], 404);
            }

            $orderData = $orderResponse->json();
            /** @var array|null $order */
            $order = $orderData['data'] ?? null;

            if (!$order) {
                return response()->json(['status' => 'failed', 'message' => 'Data order kosong'], 500);
            }

            // 2. Cek apakah sudah pernah dibayar
            $existing = Payment::where('order_id', $request->order_id)->first();
            if ($existing) {
                return response()->json(['status' => 'failed', 'message' => 'Order sudah dibayar'], 400);
            }

            // 3. Simpan Payment ke Database
            $payment = Payment::create([
                'order_id' => $request->order_id,
                'amount' => $order['total_price'] ?? 0,
                'method' => $request->method,
                'status' => 'paid'
            ]);

            // 4. Dispatch Job ke Queue
            ProcessPaymentNotification::dispatch($payment, $order)->onQueue('payment.process');
            Log::info("Dispatched ProcessPaymentNotification for payment: " . $payment->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil dan notifikasi diproses',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'product_name' => $order['product_name'] ?? 'Unknown',
                    'status' => $payment->status
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error("Payment Controller Error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['status' => 'failed', 'message' => 'Data tidak ditemukan'], 404);
        }

        $orderResponse = Http::get('http://order-service:8000/api/orders/' . $payment->order_id);
        $order = $orderResponse->json()['data'] ?? null;

        return response()->json([
            'status' => 'success',
            'data' => array_merge($payment->toArray(), ['order_details' => $order])
        ]);
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);
        if ($payment) {
            $payment->delete();
            return response()->json(['status' => 'success', 'message' => 'Deleted']);
        }
        return response()->json(['status' => 'failed'], 404);
    }
}
