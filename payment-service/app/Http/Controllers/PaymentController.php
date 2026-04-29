<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Resources\PaymentResource;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    // GET semua payment
    public function index()
    {
        $payments = Payment::all();

        return response()->json([
            'status' => 'success',
            'message' => 'List semua payment',
            'data' => PaymentResource::collection($payments)
        ]);
    }

    // GET payment by id
    public function show($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Payment tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail payment',
            'data' => new PaymentResource($payment)
        ]);
    }

    // POST payment (ambil dari OrderService)
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

        // ambil data dari OrderService
        $orderResponse = Http::get('http://127.0.0.1:8003/api/orders/' . $request->input('order_id'));

        if ($orderResponse->failed()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Order tidak ditemukan atau service tidak aktif'
            ], 404);
        }

        $responseData = $orderResponse->json();

        // handle response (data atau langsung)
        $order = isset($responseData['data']) ? $responseData['data'] : $responseData;

        // validasi total
        if (!isset($order['total'])) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Field total tidak ditemukan di OrderService'
            ], 500);
        }

        // simpan payment
        $payment = Payment::create([
            'order_id' => $request->input('order_id'),
            'amount' => $order['total'],
            'method' => $request->input('method'), // 🔥 FIX disini
            'status' => 'paid'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment berhasil ditambahkan',
            'data' => new PaymentResource($payment)
        ], 201);
    }

    // DELETE payment
    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Payment gagal dihapus, ID tidak ada'
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment berhasil dihapus'
        ]);
    }
}