<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1728033363AddSalesChannelEntrypointIds;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1728033363AddSalesChannelEntrypointIds::class)]
class Migration1728033363AddSalesChannelEntrypointIdsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $migration = new Migration1728033363AddSalesChannelEntrypointIds();
        static::assertSame(1728033363, $migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);
    }
}
