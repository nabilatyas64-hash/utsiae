<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ConsumeStockUpdate extends Command
{
    // Ini perintah yang akan dipanggil di terminal nanti
    protected $signature = 'app:consume-stock-update';
    protected $description = 'Mendengarkan RabbitMQ untuk update stok produk otomatis';

    public function handle()
    {
        try {
            // Koneksi ke RabbitMQ
            $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            // Deklarasi queue yang sama dengan di Payment Service
            $channel->queue_declare('product-stock-update', false, true, false, false);

            $this->info(" [*] Menunggu pesan dari Payment Service... Tekan CTRL+C untuk berhenti.");

            $callback = function ($msg) {
                $data = json_decode($msg->body, true);
                $this->info(" [x] Pesan Diterima: " . $msg->body);

                // Proses pengurangan stok
                $productId = $data['product_id'] ?? null;
                $quantity = $data['quantity'] ?? 0;

                if ($productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $product->decrement('stock', $quantity);
                        $this->info(" [v] Stok berhasil dikurangi! Produk: {$product->name}, Jumlah: {$quantity}");
                    } else {
                        $this->error(" [!] Produk dengan ID {$productId} tidak ditemukan.");
                    }
                }
            };

            $channel->basic_consume('product-stock-update', '', false, true, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            $this->error("Gagal menjalankan Consumer: " . $e->getMessage());
            Log::error("RabbitMQ Consumer Error: " . $e->getMessage());
        }
    }
}