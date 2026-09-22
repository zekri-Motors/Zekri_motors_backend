<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Small shared helper so order-number generation isn't duplicated between
 * BatchCarsImportService::generateOrderNumber() and
 * PreOrderCar::approveRequest(). Optional: BatchCarsImportService can be
 * updated to call OrderNumberGenerator::generate() too, to remove the
 * now-duplicated private method there.
 */
class OrderNumberGenerator
{
    public static function generate(): string
    {
        do {
            $number = 'ORD-' . now()->format('ym') . '-' . strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
