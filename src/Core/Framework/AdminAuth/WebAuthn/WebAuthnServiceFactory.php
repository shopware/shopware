<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\WebAuthn;

use Shopware\Core\Framework\Log\Package;

/**
 * Builds the {@see WebAuthnService} from the application URL.
 *
 * The Relying Party ID is the host of APP_URL (no scheme/port); the allowed origin is the full
 * APP_URL origin. WebAuthn credentials are scoped to this rpId, so passkeys registered in the
 * administration only work on the shop's host.
 *
 * @internal
 */
#[Package('framework')]
class WebAuthnServiceFactory
{
    public function __construct(
        private readonly string $appUrl,
        private readonly string $rpName = 'Shopware Admin',
    ) {
    }

    public function create(): WebAuthnService
    {
        $host = parse_url($this->appUrl, \PHP_URL_HOST) ?: 'localhost';
        $origin = $this->origin($this->appUrl);

        return new WebAuthnService($host, $this->rpName, [$origin]);
    }

    private function origin(string $url): string
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, \PHP_URL_HOST) ?: 'localhost';
        $port = parse_url($url, \PHP_URL_PORT);

        $origin = $scheme . '://' . $host;
        if ($port !== null && !\in_array($port, [80, 443], true)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }
}
