<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Server-side replay protection for inbound RFC 9421 signatures.
 *
 * UCP signatures.md §"Replay Protection" RECOMMENDS one or both of:
 *
 *   1. A short signature validity window (`expires - created ≤ 300s`).
 *   2. A nonce-store keyed by `(kid, signature)` that rejects duplicates.
 *
 * Without (2) a captured signed request can be replayed at will inside the
 * validity window. We implement (2) by inserting `(sales_channel_id, kid,
 * signature_hash, created)` into `ucp_signature_nonce` and treating
 * UNIQUE-conflict as a replay. Rows live for the maximum acceptable
 * `expires - created` window (5 min by default) plus a small grace.
 *
 * @internal
 */
#[Package('framework')]
class SignatureReplayGuard
{
    /**
     * How long an accepted signature is remembered as "seen".
     */
    public const RETENTION_SECONDS = 600;

    /**
     * Maximum acceptable signature validity window — see verifier.
     */
    public const MAX_VALIDITY_WINDOW_SECONDS = 300;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Atomically register a freshly-verified signature as seen. Throws
     * {@see UcpException::signatureInvalid()} if the signature has already
     * been observed within the retention window.
     *
     * Validity-window enforcement is done by the caller (verifier) — this
     * class only handles deduplication.
     */
    public function rememberOrThrow(
        string $salesChannelId,
        string $kid,
        string $signatureRaw,
        ?int $created
    ): void {
        // sha256 (not Hasher::hash) intentional: replay-protection nonce must
        // be cryptographically secure so an attacker cannot construct collisions.
        // @phpstan-ignore-next-line shopware.hasher
        $hash = hash('sha256', $signatureRaw);
        $createdTs = $created ?? time();
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+' . self::RETENTION_SECONDS . ' seconds')->format('Y-m-d H:i:s.v');

        try {
            $this->connection->insert('ucp_signature_nonce', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'kid' => $kid,
                'signature_hash' => $hash,
                'created' => (new \DateTimeImmutable('@' . $createdTs))->format('Y-m-d H:i:s.v'),
                'expires_at' => $expiresAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw UcpException::signatureInvalid('Signature replay detected — this signature has already been used');
        }
    }
}
