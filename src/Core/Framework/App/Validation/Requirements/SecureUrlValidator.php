<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Requirements;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Validates whether a given shop URL is a secure and valid target.
 *
 * The validator ensures that the URL: (this remains a simple validation and does not guarantee absolute correctness)
 *   - Uses the HTTPS scheme
 *   - Does not resolve to an IP address
 *   - Is not 'localhost' or a reserved domain
 *
 * @see https://www.iana.org/assignments/special-use-domain-names/special-use-domain-names.xhtml
 */
#[Package('framework')]
readonly class SecureUrlValidator
{
    private const array RESERVED_SUFFIXES = [
        '.localhost',
        '.local',
        '.test',
        '.example',
        '.invalid',
        '.onion',
        '.home.arpa',
    ];

    private const array RESERVED_EXACT = [
        'example.net',
        'example.org',
        'home.arpa',
    ];

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

        if ($this->isReserved($host)) {
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

    private function isReserved(string $host): bool
    {
        $host = strtolower($host);

        if ($host === 'localhost' || \in_array($host, self::RESERVED_EXACT, true)) {
            return true;
        }

        $dotHost = '.' . ltrim($host, '.');

        return array_any(
            self::RESERVED_SUFFIXES,
            static fn (string $suffix): bool => str_ends_with($dotHost, $suffix)
        );
    }
}
