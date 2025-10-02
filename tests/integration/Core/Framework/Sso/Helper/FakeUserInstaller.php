<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Sso\Helper;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class FakeUserInstaller
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function installBaseUserData(string $id, string $email): void
    {
        $byteLocaleId = $this->connection->fetchOne('SELECT `id` FROM `locale` WHERE `code` = :code', ['code' => 'en-GB']);

        $id = Uuid::fromHexToBytes($id);

        $sql = 'INSERT INTO `user` (`id`, `username`, `password`, `first_name`, `last_name`, `title`, `email`, `active`, `admin`, `avatar_id`, `locale_id`, `store_token`, `last_updated_password_at`, `time_zone`, `custom_fields`, `created_at`, `updated_at`) VALUES
                (?, ?, \'$53CR3D9422W0RD\', ?, ?, \'Baz\', ?, 0, 1, NULL, ?, NULL, \'2024-01-01 08:00:00.000\', \'Europe/Berlin\', NULL, \'2024-01-01 08:00:00.000\', NULL);';
        $this->connection->executeQuery($sql, [$id, $email, $email, $email, $email, $byteLocaleId]);
    }

    public function installTokenUser(string $userId, string $subject): void
    {
        $id = Uuid::randomBytes();
        $userId = Uuid::fromHexToBytes($userId);

        $sql = 'INSERT INTO `oauth_user` (`id`, `user_id`, `user_sub`, `token`, `expiry`, `created_at`, `updated_at`) VALUES
                (?, ?, ?, \'{"token": "invalid", "refreshToken": "invalid"}\', \'2024-01-01 08:00:00.000\', \'2024-01-01 08:00:00.000\', NULL);';
        $this->connection->executeQuery($sql, [$id, $userId, $subject]);
    }
}
