<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ProcessPaymentNotification implements ShouldQueue
{
    use Queueable;

    public $paymentData;
    public $orderData;

    public function __construct($payment, $order)
    {
        $this->paymentData = is_array($payment) ? $payment : $payment->toArray();
        $this->orderData   = $order;
    }

    public function handle(): void
    {
        Log::info('ProcessPaymentNotification: memproses pembayaran', [
            'payment_id' => $this->paymentData['id'] ?? null,
            'order_id'   => $this->paymentData['order_id'] ?? null,
            'amount'     => $this->paymentData['amount'] ?? null,
            'status'     => $this->paymentData['status'] ?? null,
        ]);

        // Kirim pesan ke product-stock-update queue untuk update stok produk
        $this->publishToProductStockQueue();

        Log::info('ProcessPaymentNotification: selesai diproses', [
            'payment_id' => $this->paymentData['id'] ?? null,
        ]);
    }

    private function publishToProductStockQueue(): void
    {
        try {
            // Gunakan hostname 'rabbitmq' (Docker service name), BUKAN 'localhost'
            $connection = new AMQPStreamConnection('rabbitmq', 5672, 'guest', 'guest');
            $channel    = $connection->channel();

            $channel->queue_declare('product-stock-update', false, true, false, false);

            $payload = json_encode([
                'order_id'   => $this->paymentData['order_id'] ?? null,
                'product_id' => $this->orderData['product_id'] ?? null,
                'quantity'   => $this->orderData['quantity'] ?? 1,
                'status'     => 'PAID',
            ]);

            $msg = new AMQPMessage($payload, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
            $channel->basic_publish($msg, '', 'product-stock-update');

            Log::info('Pesan dikirim ke queue product-stock-update', [
                'product_id' => $this->orderData['product_id'] ?? null,
                'quantity'   => $this->orderData['quantity'] ?? 1,
            ]);

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('RabbitMQ publishToProductStockQueue Error: ' . $e->getMessage());
        }
    }
}
