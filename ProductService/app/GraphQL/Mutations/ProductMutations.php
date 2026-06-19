<?php

namespace App\GraphQL\Mutations;

use App\Models\Product;

class ProductMutations
{
    public function create($root, array $args)
    {
        return Product::create([
            'name' => $args['name'],
            'price_per_kg' => $args['price_per_kg']
        ]);
    }

    public function update($root, array $args)
    {
        $product = Product::find($args['id']);

        if (!$product) {
            return null;
        }

        $product->update([
            'name' => $args['name'],
            'price_per_kg' => $args['price_per_kg']
        ]);

        return $product;
    }

    public function delete($root, array $args)
    {
        $product = Product::find($args['id']);

        if ($product) {
            $product->delete();
        }

        return $product;
    }
}
