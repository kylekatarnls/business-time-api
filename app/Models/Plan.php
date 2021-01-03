<?php

declare(strict_types=1);

namespace App\Models;

use App\Util\Number;
use ArrayObject;
use function App\Util\integerOrInfinity;

final class Plan extends ArrayObject
{
    public static function fromId(string $id): self
    {
        return new self([
            'title'    => config("plan.$id.title"),
            'name'     => config("plan.$id.name"),
            'price'    => (int) round(config("plan.$id.price.amount") * 100),
            'limit'    => integerOrInfinity(config("plan.$id.limit")),
            'product'  => config("plan.$id.id"),
            'currency' => config("plan.$id.price.currency"),
        ]);
    }

    public static function getPlansData(): array
    {
        static $data = null;

        if ($data === null) {
            $data = [
                'start'   => Plan::fromId('start'),
                'pro'     => Plan::fromId('pro'),
                'premium' => Plan::fromId('premium'),
            ];
        }

        return $data;
    }

    public function priceAmount(float $factor = 0.01): float
    {
        return $this['price'] * $factor;
    }

    public function price(float $factor = 0.01)
    {
        return Number::format($this->priceAmount($factor), 2);
    }

    public function with($data): array
    {
        return array_merge($this->getArrayCopy(), $data);
    }
}
