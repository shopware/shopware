<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Profile;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Validates a fetched platform profile:
 *   - top-level structure (ucp object, signing_keys array)
 *   - version string format
 *   - namespace authority binding (capability spec URL origin matches the reverse-domain authority)
 *
 * @internal
 */
#[Package('framework')]
class PlatformProfileValidator
{
    /**
     * @param array<string, mixed> $profile
     */
    public function validate(string $profileUri, array $profile): void
    {
        if (!isset($profile['ucp']) || !\is_array($profile['ucp'])) {
            throw UcpException::profileMalformed($profileUri, 'Missing top-level `ucp` object');
        }

        $ucp = $profile['ucp'];

        if (!isset($ucp['version']) || !\is_string($ucp['version']) || !UcpVersion::isValidFormat($ucp['version'])) {
            throw UcpException::profileMalformed($profileUri, 'Missing or invalid `ucp.version`');
        }

        if (isset($profile['signing_keys']) && !\is_array($profile['signing_keys'])) {
            throw UcpException::profileMalformed($profileUri, '`signing_keys` must be an array');
        }

        if (isset($ucp['capabilities']) && \is_array($ucp['capabilities'])) {
            foreach ($ucp['capabilities'] as $name => $entries) {
                if (!\is_string($name) || !\is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (\is_array($entry)) {
                        $this->validateCapabilityEntry($name, $entry);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function validateCapabilityEntry(string $capabilityName, array $entry): void
    {
        $authority = $this->extractAuthority($capabilityName);
        if ($authority === null) {
            return; // unknown namespace pattern — let intersection drop it
        }

        $spec = $entry['spec'] ?? null;
        if (\is_string($spec) && $spec !== '') {
            $specOrigin = $this->urlOrigin($spec);
            if ($specOrigin !== null && !$this->originMatchesAuthority($specOrigin, $authority)) {
                throw UcpException::profileNamespaceMismatch($capabilityName, $authority, $specOrigin);
            }
        }
    }

    /**
     * `dev.ucp.shopping.cart`        -> `ucp.dev`
     * `com.example.payments.foo`     -> `example.com`
     * `org.acme.foo`                 -> `acme.org`
     */
    private function extractAuthority(string $capabilityName): ?string
    {
        $parts = explode('.', $capabilityName);
        if (\count($parts) < 3) {
            return null;
        }
        $tld = strtolower($parts[0]);
        $domain = strtolower($parts[1]);
        if (!\in_array($tld, ['com', 'org', 'dev', 'net', 'io'], true)) {
            return null;
        }

        return $domain . '.' . $tld;
    }

    private function urlOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        return strtolower($parts['host']);
    }

    private function originMatchesAuthority(string $origin, string $authority): bool
    {
        if ($origin === $authority) {
            return true;
        }

        // also accept subdomains under the authority host
        return str_ends_with($origin, '.' . $authority);
    }
}
