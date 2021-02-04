<?php

namespace Tests\Unit\Model;

use App\Models\ApiAuthorization;
use ReflectionMethod;
use Tests\TestCase;

final class ApiAuthorizationTest extends TestCase
{
    public function testAuthorization(): void
    {
        $ziggy = $this->newZiggy();

        /** @var ApiAuthorization $auth */
        $auth = $ziggy->apiAuthorizations()->create([
            'name'  => 'Music',
            'type'  => 'domain',
            'value' => 'music.github.io',
        ]);

        $this->assertTrue($auth->accept('music.github.io'));
        $this->assertFalse($auth->accept('https://music.github.io'));
        $this->assertFalse($auth->accept('5.2.3.6'));
        $this->assertTrue($auth->needsManualVerification());
        $this->assertSame(3000, $auth->getFreeLimit(3000));
        $this->assertSame([
            'type'  => 'domain',
            'value' => 'music.github.io',
        ], $auth->pick(['type', 'value']));

        $sentence = $auth->getVerification();
        $appUrl = config('app.url');

        $this->assertStringStartsWith(
            'Avant que vous puissiez commencer à utiliser "music.github.io", ' .
            'nous devons vérifier que vous en êtes le propriétaire. ' .
            'Veuillez télécharger le fichier ' .
            '<a class="text-gray-600" href="' . $appUrl . '/authorization/verification-file/music.github.io">',
            $sentence,
        );
        $this->assertStringContainsString(
            ".html</a> et le publier pour le rendre accessible à l'URL http://music.github.io/.well-known/",
            $sentence,
        );
        $this->assertStringEndsWith(
            '.html (ou l\'équivalent en https, le dossier ".well-known" est également optionnel).',
            $sentence,
        );

        $this->assertFalse($auth->isVerified());
        $this->assertNull($auth->getFreeCount());
        $this->assertNull($auth->getPaidCount());
        $this->assertNull($auth->getBlockedCount());
        $auth->verify();
        $this->assertTrue($auth->isVerified());
        $getCountFile = new ReflectionMethod(ApiAuthorization::class, 'getCountFile');
        $getCountFile->setAccessible(true);

        $auth = ApiAuthorization::find($auth->id);
        file_put_contents($getCountFile->invoke($auth), '123');
        file_put_contents($getCountFile->invoke($auth, '-paid'), '456');
        file_put_contents($getCountFile->invoke($auth, '-blocked'), '789');

        $this->assertSame(123, $auth->getFreeCount());
        $this->assertSame(456, $auth->getPaidCount());
        $this->assertSame(789, $auth->getBlockedCount());

        /** @var ApiAuthorization $auth */
        $auth = $ziggy->apiAuthorizations()->create([
            'name'  => 'Music',
            'type'  => 'ip',
            'value' => '5.2.3.6',
        ]);

        $this->assertSame(3000, $auth->getFreeLimit(3000));
        $this->assertFalse($auth->accept('music.github.io'));
        $this->assertFalse($auth->accept('https://music.github.io'));
        $this->assertTrue($auth->accept('5.2.3.6'));
        $this->assertFalse($auth->needsManualVerification());

        $this->assertNull($auth->getFreeCount());
        $auth->verify();
        $auth = ApiAuthorization::find($auth->id);
        $this->assertSame(0, $auth->getFreeCount());
    }
}
