<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Endpoint;
use Illuminate\Database\Seeder;

/**
 * Seeds placeholder endpoints (e.g. Recharge discount). Use real URL/headers in production.
 */
class DefaultEndpointsSeeder extends Seeder
{
    public function run(): void
    {
        Endpoint::firstOrCreate(
            ['key' => 'recharge.create_discount'],
            [
                'name' => 'Recharge Create 25% Discount',
                'method' => 'POST',
                'url' => 'https://api.rechargeapps.com/placeholder/discounts',
                'timeout_sec' => 30,
                'retries' => 0,
                'request_mapper' => ['customer_id' => 'context.customer_id', 'percentage' => '25'],
                'response_mapper' => ['discount_code' => 'discount.code'],
            ]
        );
    }
}
