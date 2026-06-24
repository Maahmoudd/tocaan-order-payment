<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->getKey() === $order->user_id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->getKey() === $order->user_id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->getKey() === $order->user_id;
    }
}
