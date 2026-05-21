<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Embedded;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Issues + persists short-lived (default 15 min) EP sessions. Stored in
 * `ucp_embedded_session`; the row is the authoritative record for
 * verifying the `X-UCP-Embedded-Session` header on subsequent embedded
 * REST calls.
 *
 * @internal
 */
#[Package('framework')]
class EmbeddedSessionFactory
{
    public const TTL_SECONDS = 900;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function issue(string $cartId, string $salesChannelId, string $hostOrigin, string $kind): EmbeddedSession
    {
        $id = Uuid::randomHex();
        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+' . self::TTL_SECONDS . ' seconds');

        $this->connection->insert('ucp_embedded_session', [
            'id' => Uuid::fromHexToBytes($id),
            // sha256 (not Hasher::hash) intentional: we only persist the hash of
            // the session token so an attacker with DB read access cannot replay
            // the original token; we want cryptographic preimage resistance.
            // @phpstan-ignore-next-line shopware.hasher
            'session_token_hash' => hash('sha256', $token),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'cart_id' => $cartId,
            'host_origin' => $hostOrigin,
            'kind' => $kind,
            'created_at' => $now->format('Y-m-d H:i:s.v'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.v'),
        ]);

        return new EmbeddedSession(
            id: $id,
            token: $token,
            cartId: $cartId,
            salesChannelId: $salesChannelId,
            hostOrigin: $hostOrigin,
            kind: $kind,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Verify that a session token presented on a REST request is still valid
     * for the given cart/sales-channel.
     */
    public function verify(string $sessionToken, string $cartId, string $salesChannelId, ?string $hostOrigin): bool
    {
        if (!\is_string($hostOrigin) || $hostOrigin === '') {
            return false;
        }

        // sha256 (not Hasher::hash) intentional: pairs with the storage-side hash
        // in `issue()` above; cryptographic preimage resistance is the whole point.
        // @phpstan-ignore-next-line shopware.hasher
        $hash = hash('sha256', $sessionToken);
        $row = $this->connection->fetchOne(
            'SELECT id FROM ucp_embedded_session
             WHERE session_token_hash = ? AND cart_id = ? AND sales_channel_id = ? AND host_origin = ? AND expires_at > NOW(3)
             LIMIT 1',
            [$hash, $cartId, Uuid::fromHexToBytes($salesChannelId), $hostOrigin]
        );

        return $row !== false;
    }
}
