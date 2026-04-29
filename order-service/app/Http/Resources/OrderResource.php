<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_order' => $this->id,
            'user_id' => $this->user_id,
            'product_id' => $this->product_id,
            'berat' => $this->qty . ' kg',
            'total_bayar' => 'Rp ' . number_format($this->total_price, 0, ',', '.'),
            'dibuat_pada' => $this->created_at->format('d-m-Y H:i'),
        ];
    }
}