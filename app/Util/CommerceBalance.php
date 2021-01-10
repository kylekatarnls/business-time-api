<?php

namespace App\Util;

use Stripe\Balance;

class CommerceBalance extends Balance
{
    public static function get(): self
    {
        /** @var self $balance */
        $balance = parent::retrieve(
            ['stripe_account' => config('stripe.account_id')],
        );

        return $balance;
    }

    /**
     * @return array{
     *     object: string,
     *     live_mode: bool,
     *     available: array<array{
     *         amount: int,
     *         currency: string,
     *         source_types: array{card: int},
     *     }>,
     *     pending: array<array{
     *         amount: int,
     *         currency: string,
     *         source_types: array{card: int},
     *     }>,
     * }
     */
    public function toArray()
    {
        return parent::toArray();
    }
}
