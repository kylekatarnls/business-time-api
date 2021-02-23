<?php

namespace App\Http\Controllers;

use App\Authorization\AuthorizationFactory;
use App\Models\ApiAuthorization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AuthorizationController extends AbstractController
{
    public function create(Request $request): RedirectResponse
    {
        $types = config('app.authorizations');
        $type = count($types) === 1 ? reset($types) : $request->get('type');
        $value = rtrim(preg_replace('`^https?://`', '', trim($request->get($type) ?? '')), '/');

        if (!AuthorizationFactory::fromType($type)->accept($value)) {
            return redirect('dashboard')
                ->withInput()
                ->with('authorisationsErrors', [$type => 'format']);
        }

        try {
            $this->getUser()->apiAuthorizations()->create([
                'name'  => $request->get('name'),
                'type'  => $type,
                'value' => $value,
            ]);

            self::clearCache($type, $value);
        } catch (QueryException $exception) {
            Log::warning($exception);

            return redirect('dashboard')
                ->withInput()
                ->with('authorisationsErrors', [$type => match ((string) $exception->getCode()) {
                    '23000' => 'duplicate',
                    default => 'unknown',
                }]);
        }

        return redirect('dashboard');
    }

    public function delete(Request $request): RedirectResponse
    {
        $value = $request->get('value');
        $type = $request->get('type');
        $this->getUser()->apiAuthorizations()->where([
            'type'  => $type,
            'value' => $value,
        ])->delete();

        Log::notice('User #' . Auth::id() . ' deleted the [' . $type . '] "' . $value . '"');

        self::clearCache('ip', $value);
        self::clearCache('domain', $value);

        return redirect('dashboard');
    }

    public function getVerifyToken(string $ipOrDomain): BinaryFileResponse
    {
        /** @var ApiAuthorization $authorization */
        $authorization = $this->getUser()->apiAuthorizations()->where(['value' => $ipOrDomain])->first();

        return Response::download(
            $authorization->getVerificationInternalFile(),
            $authorization->getVerificationFileName(),
        );
    }

    public function verify(string $type, string $value): RedirectResponse
    {
        /** @var ApiAuthorization $authorization */
        $authorization = $this->getApiAuthorizations()
            ->where('type', $type)
            ->whereIn('value', $value)
            ->first();

        $error = $this->checkVerificationFile($authorization);

        if (!$error) {
            $authorization->verify();
            self::clearCache($type, $value);
        }

        return redirect('dashboard')->with([
            'verifyError' => $error,
            'verifiedAuthorization' => $authorization->id,
        ]);
    }

    private function preferHtml(Request $request): bool
    {
        foreach ($request->getAcceptableContentTypes() as $type) {
            if (Str::contains($type, 'html')) {
                return true;
            }

            if ($type === '*' || $type === '*/*') {
                return false;
            }
        }

        return false;
    }

    public function verifyIp(Request $request, string $email, string $token, ?string $ip = null): Response
    {
        $preferHtml = $this->preferHtml($request);
        $ips = array_unique($request->getClientIps());
        $authorizations = $this->getVerifiableAuthorizations($ips, $email, $ip);

        foreach ($authorizations as $authorization) {
            if ($authorization->getVerificationToken() === $token) {
                if (!$authorization->verify()) {
                    throw new RuntimeException(
                        __('Unable to verify due to unknown error, please retry.'),
                    );
                }

                self::clearCache('ip', $authorization->value);

                if ($preferHtml) {
                    return ResponseFacade::view('ip-authorized', ['authorization' => $authorization]);
                }

                return ResponseFacade::make('OK');
            }
        }

        $count = count($ips);
        $interpolations = [
            'token'    => $token,
            'ip'       => implode(', ', $ips),
            'expected' => $ip
        ];
        $error = $ip
            ? trans_choice('The token :token is not for the exposed IP address: :ip. Please access this URL from within your server with the IP :expected.|The token :token is not for any of the exposed IP addresses: :ip. Please access this URL from within your server with the IP :expected.', $count, $interpolations)
            : trans_choice('The token :token is not for the exposed IP address: :ip. Please access this URL from within your server with the IP you want to verify.|The token :token is not for any of the exposed IP addresses: :ip. Please access this URL from within your server with the IP you want to verify.', $count, $interpolations);

        $response = $preferHtml
            ? $this->getIpVerificationFailureView($error, $ips, $email, $token, $ip)
            : ResponseFacade::make(__('Error') . "\n$error");

        return $response->setStatusCode(401);
    }

    private static function getUnsecureContent(string $url): string
    {
        $request = @curl_init();
        @curl_setopt($request, CURLOPT_HEADER, 0);
        @curl_setopt($request, CURLOPT_FOLLOWLOCATION, 1);
        @curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($request, CURLOPT_URL, $url);
        @curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 0);
        @curl_setopt($request, CURLOPT_SSL_VERIFYPEER, 0);
        @curl_setopt($request, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.88 Safari/537.36');
        $response = @curl_exec($request);
        @curl_close($request);

        return $response ?: '';
    }

    private function getPossibleDeployedTokensContents(ApiAuthorization $authorization, &$fileName = null): iterable
    {
        $prefixes = ['.well-known/', ''];
        $protocols = ['https', 'http'];
        $value = $authorization->value;
        $fileName = $authorization->getVerificationFileName();

        foreach ($protocols as $protocol) {
            foreach ($prefixes as $prefix) {
                yield trim(static::getUnsecureContent("$protocol://$value/$prefix$fileName"));
            }
        }
    }

    private function isTokenDeployed(ApiAuthorization $authorization, &$fileName = null): bool
    {
        $token = $authorization->getVerificationToken();

        foreach ($this->getPossibleDeployedTokensContents($authorization, $fileName) as $content) {
            if ($token === $content) {
                return true;
            }
        }

        return false;
    }

    private function checkVerificationFile(ApiAuthorization $authorization): ?string
    {
        if (!$authorization->needsManualVerification()) {
            return __('IP needs to be verified by call from inside the server.');
        }

        if (!$this->isTokenDeployed($authorization, $fileName)) {
            return __('The URL :url did not output ":token".', [
                'url' => 'http://' . $authorization->value . '/.well-known/' . $fileName,
                'token' => $authorization->getVerificationToken(),
            ]);
        }

        if (!$authorization->verify()) {
            return __('Unable to verify due to unknown error, please retry.');
        }

        self::clearCache($authorization->type, $authorization->value);

        return null;
    }

    /**
     * @param string[]    $ips
     * @param string      $email
     * @param string      $token
     * @param string|null $ip
     *
     * @return ApiAuthorization[]
     */
    private function getVerifiableAuthorizations(array $ips, string $email, ?string $ip): Collection
    {
        if ($ip) {
            $ips = in_array($ip, $ips) ? [$ip] : [];
        }

        if (!count($ips)) {
            return collect([]);
        }

        /** @var User $user */
        $user = User::where(['email' => $email])->first();

        return $user->apiAuthorizations()
            ->where('type', 'ip')
            ->whereIn('value', $ips)
            ->get();
    }

    /**
     * @param string      $error
     * @param string[]    $ips
     * @param string      $email
     * @param string      $token
     * @param string|null $ip
     *
     * @return Response
     */
    private function getIpVerificationFailureView(string $error, array $ips, string $email, string $token, ?string $ip): Response
    {
        return ResponseFacade::view('ip-authorization-failure', [
            'ip'    => $ip,
            'ips'   => $ips,
            'error' => $error,
            'url'   => $ip
                ? route('verify-ip', [
                    'email' => urlencode($email),
                    'token' => $token,
                    'ip'    => $ip,
                ])
                : route('verify-ip-token', [
                    'email' => urlencode($email),
                    'token' => $token,
                ]),
        ]);
    }
}
