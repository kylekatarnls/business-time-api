<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Authorization;
use App\Authorization\AuthorizationFactory;
use App\Authorization\Domain;
use App\Authorization\Ip;
use App\Models\ApiAuthorization;
use InvalidArgumentException;
use Tests\TestCase;

final class DomainTest extends TestCase
{
    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = new Domain();
    }

    public function testGetName(): void
    {
        $this->assertSame('Domaine', $this->domain->getName());
    }

    public function testAccept(): void
    {
        $this->assertFalse($this->domain->accept('1.2.3.4'));
        $this->assertTrue($this->domain->accept('foo.bar'));
    }

    public function testNeedsManualVerification(): void
    {
        $this->assertTrue($this->domain->needsManualVerification());
    }

    public function testGetVerification(): void
    {
        $sentence = $this->domain->getVerification(new ApiAuthorization([
            'value' => 'google.com',
        ]));
        $appUrl = config('app.url');

        $this->assertStringStartsWith(
            'Avant que vous puissiez commencer à utiliser "google.com", ' .
            'nous devons vérifier que vous en êtes le propriétaire. ' .
            'Veuillez télécharger le fichier ' .
            '<a class="text-gray-600" href="' . $appUrl . '/authorization/verification-file/google.com">',
            $sentence,
        );
        $this->assertStringContainsString(
            ".html</a> et le publier pour le rendre accessible à l'URL http://google.com/.well-known/",
            $sentence,
        );
        $this->assertStringEndsWith(
            '.html (ou l\'équivalent en https, le dossier ".well-known" est également optionnel).',
            $sentence,
        );
    }
}
