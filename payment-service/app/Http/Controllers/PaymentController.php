<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
            // 1. Ambil data dari Order Service
            $orderResponse = Http::get('http://order-service:8000/api/orders/' . $request->order_id);

            if (!$orderResponse->successful()) {
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

            // 4. KIRIM PESAN KE RABBITMQ
            $this->publishToRabbitMQ($payment, $order);

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil dan pesan dikirim ke RabbitMQ',
                'data' => [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'product_name' => $order['product_name'] ?? 'Unknown',
                    'status' => $payment->status
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error("Payment Error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param \App\Models\Payment $payment
     * @param array $order
     */
    private function publishToRabbitMQ($payment, $order)
    {
        try {
            $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            $channel->queue_declare('product-stock-update', false, true, false, false);

            $payload = json_encode([
                'order_id'   => $payment->order_id,
                'product_id' => $order['product_id'] ?? null,
                'quantity'   => $order['quantity'] ?? 1,
                'status'     => 'PAID'
            ]);

            $msg = new AMQPMessage($payload, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $channel->basic_publish($msg, '', 'product-stock-update');

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error("RabbitMQ Publish Error: " . $e->getMessage());
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