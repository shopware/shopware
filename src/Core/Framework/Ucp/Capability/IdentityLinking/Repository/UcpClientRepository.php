<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Repository;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpClientEntity;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Two-mode client resolution:
 *
 * 1. PRE-REGISTERED CLIENTS — looked up from `ucp_oauth_client` table.
 *    Confidential clients (with a hashed secret) are validated against the
 *    presented secret.
 *
 * 2. PERMISSIONLESS PLATFORM CLIENTS — when the `client_id` is a valid HTTPS
 *    URL pointing at the platform profile, we resolve and trust the platform
 *    based on its UCP-Agent profile (this enables permissionless onboarding
 *    described in the UCP spec). Such clients are auto-registered with a row
 *    in `ucp_oauth_client` on first sight (lazy registration).
 *
 * The scope of "permissionless" trust is bounded by the Sales Channel's
 * `platform_allowlist` config — if set, only listed profile URIs may
 * auto-register.
 *
 * @internal
 */
#[Package('framework')]
class UcpClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private string $salesChannelId,
    ) {
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ucp_oauth_client WHERE sales_channel_id = :sc AND client_id = :cid LIMIT 1',
            ['sc' => Uuid::fromHexToBytes($this->salesChannelId), 'cid' => $clientIdentifier]
        );
        if (!\is_array($row)) {
            return $this->lazyRegisterPermissionlessClient($clientIdentifier);
        }

        return $this->hydrate($row);
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT is_confidential, client_secret_hash FROM ucp_oauth_client WHERE sales_channel_id = :sc AND client_id = :cid LIMIT 1',
            ['sc' => Uuid::fromHexToBytes($this->salesChannelId), 'cid' => $clientIdentifier]
        );
        if (!\is_array($row)) {
            // permissionless client — accept (PKCE provides the real protection)
            return true;
        }

        if (!(bool) $row['is_confidential']) {
            return true;
        }

        if ($clientSecret === null || $clientSecret === '') {
            return false;
        }
        $hash = $row['client_secret_hash'];
        if (!\is_string($hash) && $hash === null) {
            return false;
        }

        return password_verify($clientSecret, (string) $hash);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): UcpClientEntity
    {
        $clientId = (string) $row['client_id'];
        if ($clientId === '') {
            throw UcpException::oauthClientNotFound('(empty)');
        }
        $entity = new UcpClientEntity();
        $entity->setIdentifier($clientId);
        $entity->setName((string) $row['name']);
        $entity->setRedirectUri(json_decode((string) $row['redirect_uris'], true) ?: []);
        $entity->setConfidential((bool) $row['is_confidential']);
        $entity->setSalesChannelId(bin2hex((string) $row['sales_channel_id']));
        $entity->setAllowedScopes(json_decode((string) $row['allowed_scopes'], true) ?: []);
        $entity->setPlatformProfileUri($row['platform_profile_uri'] ?? null);

        return $entity;
    }

    private function lazyRegisterPermissionlessClient(string $clientIdentifier): ?ClientEntityInterface
    {
        if (!filter_var($clientIdentifier, \FILTER_VALIDATE_URL) || !str_starts_with($clientIdentifier, 'https://')) {
            return null;
        }

        // Optional allowlist check — performed by the AuthorizeController
        // before invoking this method. Repository stays liberal here.
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $this->connection->insert('ucp_oauth_client', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelId),
            'client_id' => $clientIdentifier,
            'name' => parse_url($clientIdentifier, \PHP_URL_HOST) ?: $clientIdentifier,
            'redirect_uris' => json_encode([]),
            'is_confidential' => 0,
            'allowed_scopes' => json_encode([]),
            'platform_profile_uri' => $clientIdentifier,
            'created_at' => $now,
        ]);

        return $this->getClientEntity($clientIdentifier);
    }
}
