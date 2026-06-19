<?php

namespace App\GraphQL\Queries;

use App\Models\Product;

class ProductQueries
{
    public function all($root, array $args)
    {
        return Product::all();
    }

    public function find($root, array $args)
    {
        return Product::find($args['id']);
    }
}
