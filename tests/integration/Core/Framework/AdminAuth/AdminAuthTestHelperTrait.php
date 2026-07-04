<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
trait AdminAuthTestHelperTrait
{
    private Connection $connection;

    private function fetchAdminUserId(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM `user` WHERE username = :username',
            ['username' => 'admin']
        );

        static::assertIsString($id);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * Inserts an active TOTP enrollment directly, the same way the (future) self-enrollment
     * controller does: the base32 seed is stored encrypted with the app-secret-derived key.
     */
    private function enrollTotp(string $userId, string $base32Secret): void
    {
        $encryptor = new SecretEncryptor((string) static::getContainer()->getParameter('kernel.secret'));

        $this->connection->insert('admin_auth_user_method', [
            'id' => Uuid::randomBytes(),
            'user_id' => Uuid::fromHexToBytes($userId),
            'type' => 'totp',
            'active' => 1,
            'label' => 'Test authenticator',
            'secret' => $encryptor->encrypt($base32Secret),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * Decodes the payload of a JWT without verifying the signature (the token was just issued by
     * the code under test; the tests only inspect its claims).
     *
     * @return array{jti: string, sub: string, scopes: list<string>}
     */
    private function decodeTokenPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        static::assertCount(3, $parts, 'expected a JWS compact token');

        $payload = json_decode(
            (string) base64_decode(strtr($parts[1], '-_', '+/'), true),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        static::assertIsArray($payload);
        static::assertIsString($payload['jti'] ?? null);
        static::assertIsString($payload['sub'] ?? null);
        static::assertIsArray($payload['scopes'] ?? null);

        return [
            'jti' => $payload['jti'],
            'sub' => $payload['sub'],
            'scopes' => array_values(array_map('strval', $payload['scopes'])),
        ];
    }
}
