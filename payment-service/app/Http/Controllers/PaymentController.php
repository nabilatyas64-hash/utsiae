<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    // GET semua payment
    public function index()
    {
        return response()->json(Payment::all());
    }

    // GET payment by id
    public function show($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment tidak ditemukan'
            ], 404);
        }

        return response()->json($payment);
    }

    // POST payment (ambil dari OrderService)
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer',
            'method' => 'required|string'
        ]);

        // 🔥 ambil data dari OrderService
        $orderResponse = Http::get('http://127.0.0.1:8003/api/orders/' . $request->input('order_id'));

        if ($orderResponse->failed()) {
            return response()->json([
                'message' => 'Order tidak ditemukan atau service tidak aktif'
            ], 404);
        }

        $responseData = $orderResponse->json();

        // 🔥 handle response (data atau langsung)
        $order = isset($responseData['data']) ? $responseData['data'] : $responseData;

        // ❗ validasi total
        if (!isset($order['total'])) {
            return response()->json([
                'message' => 'Field total tidak ditemukan di OrderService'
            ], 500);
        }

        // 🔥 simpan payment
        $payment = Payment::create([
            'order_id' => $request->input('order_id'),
            'amount' => $order['total'],
            'method' => $request->input('method'), // 🔥 FIX disini
            'status' => 'paid'
        ]);

        return response()->json([
            'message' => 'Payment berhasil',
            'data' => $payment
        ], 201);
    }

    // DELETE payment
    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment tidak ditemukan'
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment berhasil dihapus'
        ]);
    }
}