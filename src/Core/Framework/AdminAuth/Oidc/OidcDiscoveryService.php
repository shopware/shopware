<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Oidc;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches OpenID Connect discovery documents ({issuer}/.well-known/openid-configuration) and
 * resolves the effective endpoints of a provider: explicitly configured endpoints win, the
 * (cached) discovery document fills the gaps.
 *
 * @internal
 */
#[Package('framework')]
class OidcDiscoveryService
{
    private const WELL_KNOWN_PATH = '/.well-known/openid-configuration';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Fetch a discovery document (uncached — used by the admin "discover" action, which must
     * always see the current state).
     *
     * @return array{issuer: string, authorizationEndpoint: string, tokenEndpoint: string, jwksUri: string, scopes: list<string>}
     */
    public function discover(string $issuerOrDiscoveryUrl): array
    {
        $url = $this->discoveryUrl($issuerOrDiscoveryUrl);

        try {
            $document = $this->httpClient->request('GET', $url, ['timeout' => 10])->toArray(false);
        } catch (\Throwable $exception) {
            throw AdminAuthException::oidcDiscoveryFailed($url, $exception);
        }

        $authorizationEndpoint = $this->stringField($document, 'authorization_endpoint');
        $tokenEndpoint = $this->stringField($document, 'token_endpoint');
        $jwksUri = $this->stringField($document, 'jwks_uri');

        if ($authorizationEndpoint === null || $tokenEndpoint === null || $jwksUri === null) {
            throw AdminAuthException::oidcDiscoveryFailed($url);
        }

        return [
            'issuer' => $this->stringField($document, 'issuer') ?? rtrim($issuerOrDiscoveryUrl, '/'),
            'authorizationEndpoint' => $authorizationEndpoint,
            'tokenEndpoint' => $tokenEndpoint,
            'jwksUri' => $jwksUri,
            'scopes' => $this->scopes($document),
        ];
    }

    /**
     * The effective endpoints for a provider. Explicit configuration wins; missing endpoints are
     * filled from the provider's discovery document, fetched lazily and cached.
     *
     * @return array{issuer: ?string, authorizationEndpoint: string, tokenEndpoint: string, jwksUri: string}
     */
    public function resolveEndpoints(AdminAuthProvider $provider): array
    {
        $discovered = null;

        if (
            ($provider->authorizationEndpoint === null || $provider->tokenEndpoint === null || $provider->jwksUri === null)
            && $provider->discoveryUrl !== null
        ) {
            $discovered = $this->cachedDiscover($provider->discoveryUrl);
        }

        $authorizationEndpoint = $provider->authorizationEndpoint ?? $discovered['authorizationEndpoint'] ?? null;
        $tokenEndpoint = $provider->tokenEndpoint ?? $discovered['tokenEndpoint'] ?? null;
        $jwksUri = $provider->jwksUri ?? $discovered['jwksUri'] ?? null;

        if ($authorizationEndpoint === null || $tokenEndpoint === null || $jwksUri === null) {
            throw AdminAuthException::providerMisconfigured(
                $provider->label,
                'configure a discovery_url or the explicit authorization/token/JWKS endpoints'
            );
        }

        return [
            'issuer' => $provider->issuer ?? $discovered['issuer'] ?? null,
            'authorizationEndpoint' => $authorizationEndpoint,
            'tokenEndpoint' => $tokenEndpoint,
            'jwksUri' => $jwksUri,
        ];
    }

    /**
     * @return array{issuer: string, authorizationEndpoint: string, tokenEndpoint: string, jwksUri: string, scopes: list<string>}
     */
    private function cachedDiscover(string $discoveryUrl): array
    {
        $item = $this->cache->getItem('admin_auth.oidc_discovery.' . Hasher::hash($discoveryUrl));

        if ($item->isHit()) {
            /** @var array{issuer: string, authorizationEndpoint: string, tokenEndpoint: string, jwksUri: string, scopes: list<string>} $cached */
            $cached = $item->get();

            return $cached;
        }

        $discovered = $this->discover($discoveryUrl);

        $item->set($discovered);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $discovered;
    }

    private function discoveryUrl(string $issuerOrDiscoveryUrl): string
    {
        $url = rtrim(trim($issuerOrDiscoveryUrl), '/');

        if ($url === '' || !preg_match('#^https?://#', $url)) {
            throw AdminAuthException::oidcDiscoveryFailed($issuerOrDiscoveryUrl);
        }

        if (str_ends_with($url, self::WELL_KNOWN_PATH)) {
            return $url;
        }

        return $url . self::WELL_KNOWN_PATH;
    }

    /**
     * The requested scopes, limited to what the provider claims to support.
     *
     * @param array<string, mixed> $document
     *
     * @return list<string>
     */
    private function scopes(array $document): array
    {
        $supported = $document['scopes_supported'] ?? null;
        $wanted = ['openid', 'profile', 'email'];

        if (\is_array($supported)) {
            $available = array_values(array_filter($wanted, static fn (string $scope): bool => \in_array($scope, $supported, true)));
            if ($available !== []) {
                return $available;
            }
        }

        return $wanted;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function stringField(array $document, string $key): ?string
    {
        $value = $document[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
