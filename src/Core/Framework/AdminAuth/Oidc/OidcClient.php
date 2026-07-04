<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Oidc;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Minimal, self-contained OpenID Connect client (authorization code flow): builds the authorize
 * URL, exchanges the code at the provider token endpoint and fetches/caches the provider JWKS used
 * to verify the id_token.
 *
 * @internal
 */
#[Package('framework')]
class OidcClient
{
    private const JWKS_CACHE_TTL = 3600;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly OidcDiscoveryService $discoveryService,
    ) {
    }

    public function buildAuthorizeUrl(AdminAuthProvider $provider, string $state, string $nonce, string $redirectUri): string
    {
        $endpoints = $this->discoveryService->resolveEndpoints($provider);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId($provider),
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $provider->scopes),
            'state' => $state,
            'nonce' => $nonce,
        ]);

        return $endpoints['authorizationEndpoint'] . '?' . $query;
    }

    /**
     * Exchange an authorization code for the token set and return the raw id_token (JWT).
     */
    public function exchangeCode(AdminAuthProvider $provider, string $code, string $redirectUri): string
    {
        $endpoints = $this->discoveryService->resolveEndpoints($provider);

        $response = $this->httpClient->request('POST', $endpoints['tokenEndpoint'], [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $this->clientId($provider),
                'client_secret' => $this->clientSecret($provider),
            ],
        ]);

        $data = $response->toArray(false);

        if (!isset($data['id_token']) || !\is_string($data['id_token'])) {
            throw AdminAuthException::oidcTokenResponseInvalid();
        }

        return $data['id_token'];
    }

    /**
     * Fetch the provider JWKS, cached for an hour.
     *
     * @return array<int, array<string, mixed>> the `keys` array of the JWKS document
     */
    public function getJwks(AdminAuthProvider $provider): array
    {
        $item = $this->cache->getItem('admin_auth.jwks.' . $provider->id);

        if ($item->isHit()) {
            /** @var array<int, array<string, mixed>> $cached */
            $cached = $item->get();

            return $cached;
        }

        $endpoints = $this->discoveryService->resolveEndpoints($provider);

        $data = $this->httpClient->request('GET', $endpoints['jwksUri'])->toArray(false);
        $keys = \is_array($data['keys'] ?? null) ? array_values($data['keys']) : [];

        $item->set($keys);
        $item->expiresAfter(self::JWKS_CACHE_TTL);
        $this->cache->save($item);

        return $keys;
    }

    private function clientId(AdminAuthProvider $provider): string
    {
        if ($provider->clientId === '') {
            throw AdminAuthException::providerMisconfigured($provider->label, 'missing client id');
        }

        return $provider->clientId;
    }

    private function clientSecret(AdminAuthProvider $provider): string
    {
        if ($provider->clientSecret === '') {
            throw AdminAuthException::providerMisconfigured($provider->label, 'missing client secret');
        }

        return $provider->clientSecret;
    }
}
