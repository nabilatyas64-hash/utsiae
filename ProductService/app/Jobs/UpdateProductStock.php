<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateProductStock implements ShouldQueue
{
    use Queueable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $productId = $this->data['product_id'] ?? null;
        $quantity = $this->data['quantity'] ?? null;

        if (!$productId || !$quantity) return;

        $product = Product::find($productId);

        if ($product) {
            $product->decrement('stock', $quantity);
        }
    }
}
