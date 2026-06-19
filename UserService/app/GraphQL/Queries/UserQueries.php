<?php

namespace App\GraphQL\Queries;

use App\Models\User;

class UserQueries
{
    public function all($root, array $args)
    {
        return User::all();
    }

    public function find($root, array $args)
    {
        return User::find($args['id']);
    }
}
