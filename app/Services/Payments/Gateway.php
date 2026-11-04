<?php

namespace App\Services\Payments;

use App\Models\Order;

interface Gateway
{
    /** Begins payment and returns the URL the student should be sent to. */
    public function start(Order $order): string;

    /** Confirms with the provider whether the order has been paid. */
    public function verify(Order $order): bool;
}
