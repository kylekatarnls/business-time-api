<?php

namespace App\Authorization;

use App\Models\ApiAuthorization;

interface Authorization
{
    public function getName(): string;

    public function getVerification(ApiAuthorization $authorization): string;

    public function accept(string $value): bool;

    public function needsManualVerification(): bool;
}
