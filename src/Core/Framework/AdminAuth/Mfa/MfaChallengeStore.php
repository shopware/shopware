<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Mfa;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\AdminSecondFactorGrantTest;

/**
 * DBAL-backed store for ephemeral second-factor challenges.
 *
 * A challenge binds a pending access token (by its jti) to a single login attempt: it records which
 * factor types may satisfy it, an attempt counter, a consumed flag and an expiry. It is intentionally
 * a plain table (not a DAL entity) because it is short-lived auth infrastructure, like a rate-limit row.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AdminSecondFactorGrantTest
 */
#[Package('framework')]
class MfaChallengeStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Create a challenge. The pending_jti is temporarily set to the (unique) challenge id and must be
     * bound to the issued token's real jti via {@see bindJti()} immediately afterwards.
     *
     * @param list<string> $allowedMethods
     */
    public function create(string $userId, array $allowedMethods, int $ttlSeconds): string
    {
        $id = Uuid::randomHex();
        $now = $this->clock->now();

        $this->connection->insert('admin_auth_mfa_challenge', [
            'id' => Uuid::fromHexToBytes($id),
            'user_id' => Uuid::fromHexToBytes($userId),
            'pending_jti' => 'unbound:' . $id,
            'allowed_methods' => json_encode(array_values($allowedMethods), \JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'consumed' => 0,
            'expires_at' => $now->modify(\sprintf('+%d seconds', $ttlSeconds))->format('Y-m-d H:i:s.v'),
            'created_at' => $now->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    public function bindJti(string $challengeId, string $pendingJti): void
    {
        $this->connection->executeStatement(
            'UPDATE admin_auth_mfa_challenge SET pending_jti = :jti WHERE id = :id',
            ['jti' => $pendingJti, 'id' => Uuid::fromHexToBytes($challengeId)]
        );
    }

    public function findByJti(string $pendingJti): ?MfaChallenge
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM admin_auth_mfa_challenge WHERE pending_jti = :jti',
            ['jti' => $pendingJti]
        );

        if ($row === false) {
            return null;
        }

        /** @var list<string> $allowed */
        $allowed = json_decode((string) $row['allowed_methods'], true) ?: [];

        return new MfaChallenge(
            id: Uuid::fromBytesToHex($row['id']),
            userId: Uuid::fromBytesToHex($row['user_id']),
            pendingJti: (string) $row['pending_jti'],
            allowedMethods: $allowed,
            attempts: (int) $row['attempts'],
            consumed: (bool) $row['consumed'],
            expiresAt: new \DateTimeImmutable((string) $row['expires_at']),
        );
    }

    public function incrementAttempts(string $challengeId): void
    {
        $this->connection->executeStatement(
            'UPDATE admin_auth_mfa_challenge SET attempts = attempts + 1 WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($challengeId)]
        );
    }

    public function consume(string $challengeId): void
    {
        $this->connection->executeStatement(
            'UPDATE admin_auth_mfa_challenge SET consumed = 1 WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($challengeId)]
        );
    }

    public function deleteExpired(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM admin_auth_mfa_challenge WHERE expires_at < :now',
            ['now' => $this->clock->now()->format('Y-m-d H:i:s.v')]
        );
    }
}
