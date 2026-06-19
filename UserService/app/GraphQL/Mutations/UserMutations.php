<?php

namespace App\GraphQL\Mutations;

use App\Models\User;

class UserMutations
{
    public function create($root, array $args)
    {
        return User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'phone' => $args['phone'] ?? null,
            'address' => $args['address'] ?? null,
        ]);
    }

    public function update($root, array $args)
    {
        $user = User::find($args['id']);

        if (!$user) {
            return null;
        }

        $user->update([
            'name' => $args['name'],
            'email' => $args['email'],
            'phone' => $args['phone'] ?? null,
            'address' => $args['address'] ?? null,
        ]);

        return $user;
    }

    public function delete($root, array $args)
    {
        $user = User::find($args['id']);

        if ($user) {
            $user->delete();
        }

        return $user;
    }
}
