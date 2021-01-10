<?php

namespace App\Authorization;

use App\Models\ApiAuthorization;

class Domain implements Authorization
{
    public function getName(): string
    {
        return __('Domain');
    }

    public function getVerification(ApiAuthorization $authorization): string
    {
        $fileName = $authorization->getVerificationFileName();
        $value = $authorization->value;

        return __('Before you can start to use ":value", we need to verify you own it. Please download the following file :link and upload it to make it available at the URL :url (or equivalent with https, ".well-known" directory is also optional).', [
            'value' => $value,
            'link' => '<a class="text-gray-600" href="'. route('authorization-verification', ['ipOrDomain' => $value]) . '">' . $fileName . '</a>',
            'url' => 'http://' . $value . '/.well-known/' . $fileName,
        ]);
    }

    public function accept(string $value): bool
    {
        return (bool) preg_match('/^((?!-)[A-Za-z0-9-]{1,63}(?<!-)\\.)+[A-Za-z]{2,6}$/', $value);
    }

    public function needsManualVerification(): bool
    {
        return true;
    }
}
