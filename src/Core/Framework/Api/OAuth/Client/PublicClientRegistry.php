<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Client;

use League\OAuth2\Server\RedirectUriValidators\RedirectUriValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * Holds the public OAuth clients configured under `shopware.api.oauth_clients`.
 *
 * Public clients cannot keep a secret (CLI tools, native apps) and are therefore limited to the
 * authorization code grant with PKCE and the refresh token grant. Redirect URIs are matched exactly,
 * except for loopback URIs (`http://127.0.0.1`, `http://[::1]`) where any port is accepted (RFC 8252 §7.3).
 *
 * @internal
 */
#[Package('framework')]
final class PublicClientRegistry
{
    public const GRANT_TYPES = ['authorization_code', 'refresh_token'];

    /**
     * @param array<string, array{name: string, redirect_uris: list<string>}> $clients
     */
    public function __construct(private readonly array $clients)
    {
    }

    public function has(string $clientId): bool
    {
        return isset($this->clients[$clientId]);
    }

    public function get(string $clientId): ?ApiClient
    {
        if ($clientId === '' || !isset($this->clients[$clientId])) {
            return null;
        }

        $client = $this->clients[$clientId];

        return new ApiClient(
            $clientId,
            writeAccess: true,
            name: $client['name'],
            confidential: false,
            redirectUris: array_values($client['redirect_uris']),
            grantTypes: self::GRANT_TYPES,
        );
    }

    public function isRedirectUriAllowed(string $clientId, string $redirectUri): bool
    {
        if (!isset($this->clients[$clientId])) {
            return false;
        }

        return (new RedirectUriValidator(array_values($this->clients[$clientId]['redirect_uris'])))
            ->validateRedirectUri($redirectUri);
    }
}
