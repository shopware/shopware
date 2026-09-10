<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Client;

use League\OAuth2\Server\RedirectUriValidators\RedirectUriValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\OAuthClient\OAuthClientCollection;

/**
 * Resolves configured and database-backed public OAuth clients.
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

    public const DATABASE_CLIENT_PREFIX = 'oauth-';

    /**
     * @param array<string, array{name: string, redirect_uris: list<string>}> $clients
     * @param EntityRepository<OAuthClientCollection>|null $repository
     */
    public function __construct(
        private readonly array $clients,
        private readonly ?EntityRepository $repository = null,
    ) {
    }

    public function has(string $clientId): bool
    {
        return $this->get($clientId) !== null;
    }

    public function get(string $clientId): ?ApiClient
    {
        if ($clientId === '') {
            return null;
        }

        // Configuration remains authoritative and cannot be overwritten through the Admin API.
        $client = $this->clients[$clientId] ?? null;
        if ($client === null && $this->isDatabaseClient($clientId) && $this->repository !== null) {
            $id = substr($clientId, \strlen(self::DATABASE_CLIENT_PREFIX));
            if (!Uuid::isValid($id)) {
                return null;
            }

            $entity = $this->repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
            if ($entity === null || !$entity->isActive()) {
                return null;
            }

            $client = ['name' => $entity->getName(), 'redirect_uris' => $entity->getRedirectUris()];
        }

        if ($client === null) {
            return null;
        }

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
        $client = $this->get($clientId);
        if ($client === null) {
            return false;
        }

        return (new RedirectUriValidator($client->getRedirectUri()))
            ->validateRedirectUri($redirectUri);
    }

    public function isDatabaseClient(string $clientId): bool
    {
        return str_starts_with($clientId, self::DATABASE_CLIENT_PREFIX) && !isset($this->clients[$clientId]);
    }
}
