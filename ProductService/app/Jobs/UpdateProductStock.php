<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateProductStock implements ShouldQueue
{
    use Queueable;

    protected $productId;
    protected $quantity;

    public function __construct($productId, $quantity)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);

        if ($product) {
            $product->decrement('stock', $this->quantity);
        }
    }
}
