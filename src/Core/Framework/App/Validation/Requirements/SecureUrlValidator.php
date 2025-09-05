<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Requirements;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * SecureUrlValidator
 *
 * Validates whether a given shop URL is a secure and valid target.
 *
 * the validator ensures that the URL: (this remains a simple validation and does not guarantee absolute correctness)
 *   - Uses the HTTPS scheme
 *   - Does not resolve to an IP address
 *   - Is not 'localhost'
 */
#[Package('framework')]
readonly class SecureUrlValidator
{
    public function isValidTarget(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host)) {
            return false;
        }

        if (!$this->hasHttpsScheme($url)) {
            return false;
        }

        if ($this->isIpAddress($host)) {
            return false;
        }

        if ($this->isLocalhost($host)) {
            return false;
        }

        return true;
    }

    private function hasHttpsScheme(string $url): bool
    {
        return parse_url($url, \PHP_URL_SCHEME) === 'https';
    }

    private function isIpAddress(string $host): bool
    {
        $cleanHost = trim($host, '[]');

        return filter_var($cleanHost, \FILTER_VALIDATE_IP) !== false;
    }

    private function isLocalhost(string $host): bool
    {
        return strtolower($host) === 'localhost';
    }
}
