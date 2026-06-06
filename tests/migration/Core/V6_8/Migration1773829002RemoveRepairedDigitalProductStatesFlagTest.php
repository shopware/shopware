<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_8\Migration1773829002RemoveRepairedDigitalProductStatesFlag;

/**
 * @internal
 */
#[CoversClass(Migration1773829002RemoveRepairedDigitalProductStatesFlag::class)]
class Migration1773829002RemoveRepairedDigitalProductStatesFlagTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1773829002, (new Migration1773829002RemoveRepairedDigitalProductStatesFlag())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $storage = new MySQLKeyValueStorage($this->connection);
        $storage->set('core.repaired_digital_product_states', true);

        static::assertTrue($storage->has('core.repaired_digital_product_states'));

        $migration = new Migration1773829002RemoveRepairedDigitalProductStatesFlag();
        static::assertSame(1773829002, $migration->getCreationTimestamp());

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $storage->reset();
        static::assertFalse($storage->has('core.repaired_digital_product_states'));
    }
}
