<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1673860323ChangeDefaultProductComparisonIcon;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1673860323ChangeDefaultProductComparisonIcon::class)]
class Migration1673860323ChangeDefaultProductComparisonIconTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationChangesIcon(): void
    {
        $id = Uuid::randomBytes();

        $this->connection->insert(
            'sales_channel_type',
            [
                'id' => $id,
                'icon_name' => 'default-object-rocket',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $migration = new Migration1673860323ChangeDefaultProductComparisonIcon();

        $migration->update($this->connection);

        /** @var array{icon_name: string} $result */
        $result = $this->connection->fetchAssociative('SELECT icon_name FROM `sales_channel_type` WHERE `id` = :id', [
            'id' => $id,
        ]);

        static::assertEquals('regular-rocket', $result['icon_name']);
    }
}
