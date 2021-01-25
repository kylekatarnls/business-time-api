<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Authorization;
use App\Authorization\AuthorizationFactory;
use App\Authorization\Domain;
use App\Authorization\Ip;
use InvalidArgumentException;
use Tests\TestCase;

final class AuthorizationFactoryTest extends TestCase
{
    public function testFromTypeException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown type 'not-exist'");

        AuthorizationFactory::fromType('not-exist');
    }

    public function testFromType(): void
    {
        $authorization = AuthorizationFactory::fromType('ip');

        $this->assertInstanceOf(Authorization::class, $authorization);
        $this->assertInstanceOf(Ip::class, $authorization);
        $this->assertSame('Adresse IP', $authorization->getName());
        $this->assertTrue($authorization->accept('1.2.3.4'));
        $this->assertFalse($authorization->accept('foo.bar'));
        $this->assertSame($authorization, AuthorizationFactory::fromType('ip'));

        $authorization = AuthorizationFactory::fromType('domain');

        $this->assertInstanceOf(Authorization::class, $authorization);
        $this->assertInstanceOf(Domain::class, $authorization);
        $this->assertSame('Domaine', $authorization->getName());
        $this->assertFalse($authorization->accept('1.2.3.4'));
        $this->assertTrue($authorization->accept('foo.bar'));
        $this->assertSame($authorization, AuthorizationFactory::fromType('domain'));
    }
}
