<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Bindings are kept outside the PHP session: the registration request races the
 * page load after login, and whole-blob session writes would silently drop it.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionStorageTest
 */
#[Package('framework')]
class DeviceBoundSessionStorage
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findByContextToken(string $contextToken): ?DeviceBoundSession
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM device_bound_session WHERE context_token = :token',
            ['token' => $contextToken],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(string $id): ?DeviceBoundSession
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM device_bound_session WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $publicKey
     *
     * @return bool false when the context token is already bound
     */
    public function insert(string $id, string $contextToken, array $publicKey, string $cookieHash, \DateTimeImmutable $now, \DateTimeImmutable $expiresAt): bool
    {
        $this->connection->executeStatement(
            'DELETE FROM device_bound_session WHERE expires_at < :now',
            ['now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
        );

        $affected = $this->connection->executeStatement(
            'INSERT IGNORE INTO device_bound_session (id, context_token, public_key, cookie_hash, refreshed_at, expires_at, created_at)
             VALUES (:id, :token, :publicKey, :cookieHash, :now, :expiresAt, :now)',
            [
                'id' => Uuid::fromHexToBytes($id),
                'token' => $contextToken,
                'publicKey' => json_encode($publicKey, \JSON_THROW_ON_ERROR),
                'cookieHash' => $cookieHash,
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );

        return $affected === 1;
    }

    public function storeChallenge(string $id, string $challenge): void
    {
        $this->connection->executeStatement(
            'UPDATE device_bound_session SET challenge = :challenge WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id), 'challenge' => $challenge],
        );
    }

    /**
     * Consumes the challenge and rotates the cookie in one statement, so a challenge can only be answered once.
     *
     * @return bool false when the challenge was already consumed or replaced
     */
    public function rotateCookie(string $id, string $challenge, string $cookieHash, \DateTimeImmutable $now, \DateTimeImmutable $expiresAt): bool
    {
        $affected = $this->connection->executeStatement(
            'UPDATE device_bound_session
             SET previous_cookie_hash = cookie_hash, cookie_hash = :cookieHash, challenge = NULL, refreshed_at = :now, expires_at = :expiresAt
             WHERE id = :id AND challenge = :challenge',
            [
                'id' => Uuid::fromHexToBytes($id),
                'challenge' => $challenge,
                'cookieHash' => $cookieHash,
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );

        return $affected === 1;
    }

    public function delete(string $id): void
    {
        $this->connection->delete('device_bound_session', ['id' => Uuid::fromHexToBytes($id)]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DeviceBoundSession
    {
        $publicKey = json_decode((string) $row['public_key'], true, flags: \JSON_THROW_ON_ERROR);

        return new DeviceBoundSession(
            id: Uuid::fromBytesToHex((string) $row['id']),
            contextToken: (string) $row['context_token'],
            publicKey: \is_array($publicKey) ? $publicKey : [],
            cookieHash: (string) $row['cookie_hash'],
            previousCookieHash: $row['previous_cookie_hash'] !== null ? (string) $row['previous_cookie_hash'] : null,
            challenge: $row['challenge'] !== null ? (string) $row['challenge'] : null,
            refreshedAt: new \DateTimeImmutable((string) $row['refreshed_at']),
        );
    }
}
