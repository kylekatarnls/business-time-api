<?php

namespace App\Authorization;

use InvalidArgumentException;

final class AuthorizationFactory
{
    public static function fromType(string $type): Authorization
    {
        static $authorizations = [];

        if (isset($authorizations[$type])) {
            return $authorizations[$type];
        }

        $class = '\\App\\Authorization\\' . ucfirst($type);

        if (class_exists($class)) {
            $authorizations[$type] = new $class();

            return $authorizations[$type];
        }

        throw new InvalidArgumentException("Unknown type '$type'");
    }
}
