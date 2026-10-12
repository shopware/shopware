<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1782906166RepairUserActiveDefault;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1782906166RepairUserActiveDefault::class)]
class Migration1782906166RepairUserActiveDefaultTest extends TestCase
{
    private Connection $connection;

    private Migration1782906166RepairUserActiveDefault $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1782906166RepairUserActiveDefault();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1782906166, $this->migration->getCreationTimestamp());
    }

    public function testMigrationRepairsInactiveUsersWhenDefaultIsFalse(): void
    {
        $originalDefault = $this->activeColumnDefault();
        $inactiveUserIds = $this->fetchInactiveUserIds();
        $userId = null;

        try {
            $this->setActiveColumnDefault(0);
            $userId = $this->createUser();

            static::assertSame('0', $this->activeColumnDefault());
            static::assertFalse($this->isUserActive($userId));

            $this->migration->update($this->connection);
            $this->migration->update($this->connection);

            static::assertSame('1', $this->activeColumnDefault());
            static::assertTrue($this->isUserActive($userId));
        } finally {
            $this->restoreInactiveUsers($inactiveUserIds);

            if ($userId !== null) {
                $this->deleteUser($userId);
            }

            $this->setActiveColumnDefault((int) $originalDefault);
        }
    }

    public function testMigrationDoesNothingWhenDefaultIsAlreadyTrue(): void
    {
        $originalDefault = $this->activeColumnDefault();
        $userId = null;

        try {
            $this->setActiveColumnDefault(1);
            $userId = $this->createUser(active: 0);

            $this->migration->update($this->connection);

            static::assertSame('1', $this->activeColumnDefault());
            static::assertFalse($this->isUserActive($userId));
        } finally {
            if ($userId !== null) {
                $this->deleteUser($userId);
            }

            $this->setActiveColumnDefault((int) $originalDefault);
        }
    }

    private function activeColumnDefault(): string
    {
        return (string) TableHelper::getColumnOfTable($this->connection, 'user', 'active')->defaultValue;
    }

    private function setActiveColumnDefault(int $default): void
    {
        $this->connection->executeStatement(
            \sprintf('ALTER TABLE `user` MODIFY COLUMN `active` TINYINT(1) NOT NULL DEFAULT %d', $default)
        );
    }

    /**
     * @return list<string>
     */
    private function fetchInactiveUserIds(): array
    {
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `user` WHERE `active` = 0');

        foreach ($ids as $id) {
            static::assertIsString($id);
        }

        /** @var list<string> $ids */
        return $ids;
    }

    /**
     * @param list<string> $ids
     */
    private function restoreInactiveUsers(array $ids): void
    {
        foreach ($ids as $id) {
            $this->connection->update('user', ['active' => 0], ['id' => Uuid::fromHexToBytes($id)]);
        }
    }

    private function createUser(?int $active = null): string
    {
        $id = Uuid::randomBytes();
        $suffix = Uuid::randomHex();
        $data = [
            'id' => $id,
            'username' => 'migration-active-' . $suffix,
            'password' => password_hash('password123', \PASSWORD_DEFAULT),
            'first_name' => 'Migration',
            'last_name' => 'Active',
            'email' => 'migration-active-' . $suffix . '@example.com',
            'locale_id' => $this->fetchLocaleId(),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        if ($active !== null) {
            $data['active'] = $active;
        }

        $this->connection->insert('user', $data);

        return $id;
    }

    private function fetchLocaleId(): string
    {
        $localeId = $this->connection->fetchOne('SELECT `id` FROM `locale` LIMIT 1');

        static::assertIsString($localeId);

        return $localeId;
    }

    private function isUserActive(string $userId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT `active` FROM `user` WHERE `id` = :id',
            ['id' => $userId]
        );
    }

    private function deleteUser(string $userId): void
    {
        $this->connection->delete('user', ['id' => $userId]);
    }
}
