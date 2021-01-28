<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Ip;
use App\Models\ApiAuthorization;
use Tests\TestCase;

final class IpTest extends TestCase
{
    private Ip $ip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ip = new Ip();
    }

    public function testGetName(): void
    {
        $this->assertSame('Adresse IP', $this->ip->getName());
    }

    public function testAccept(): void
    {
        $this->assertTrue($this->ip->accept('1.2.3.4'));
        $this->assertFalse($this->ip->accept('foo.bar'));
    }

    public function testNeedsManualVerification(): void
    {
        $this->assertFalse($this->ip->needsManualVerification());
    }

    public function testGetVerification(): void
    {
        /** @var ApiAuthorization $authorization */
        $authorization = $this->newZiggy()->apiAuthorizations()->create([
            'name' => 'Ultimate central server',
            'type' => 'ip',
            'value' => '189.204.12.55',
        ]);
        $sentence = $this->ip->getVerification($authorization);
        $appUrl = config('app.url');

        $this->assertStringStartsWith(
            'Avant que vous puissiez commencer à utiliser "189.204.12.55", ' .
            'nous devons vérifier que vous en êtes le propriétaire. ' .
            'Veuillez envoyer une requête GET depuis votre serveur ' .
            '(en utilisant <code>wget</code>, <code>curl</code> ou autre) ' .
            'vers <a class="text-gray-600" href="' . $appUrl . '/verify-ip/ziggy%40star.dust/',
            $sentence,
        );
        $this->assertStringContainsString(
            '">' . $appUrl . '/verify-ip/ziggy%40star.dust/',
            $sentence,
        );
        $this->assertStringEndsWith(
            '.html</a> avec la même adresse IP exposée en tant que client.',
            $sentence,
        );
    }
}
