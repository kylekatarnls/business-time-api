<?php

namespace App\Authorization;

use App\Models\ApiAuthorization;

class Ip implements Authorization
{
    public function getName(): string
    {
        return __('IP Address');
    }

    public function getVerification(ApiAuthorization $authorization): string
    {
        $token = $authorization->getVerificationToken();
        $value = $authorization->value;
        $url = route('verify-ip', [
            'email' => urlencode($authorization->user->email),
            'token' => $token,
        ]);

        return __('Before you can start to use ":value", we need to verify you own it. Please send a GET request from within your server (using <code>wget</code>, <code>curl</code> or any tool) to :link with the same IP address exposed as sender.', [
            'value' => $value,
            'link' => '<a class="text-gray-600" href="'. $url . '">' . $url . '</a>',
        ]);
    }

    public function accept(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public function needsManualVerification(): bool
    {
        return false;
    }
}
