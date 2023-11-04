<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1591253089OrderDeeplinkForMailTemplates;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1591253089OrderDeeplinkForMailTemplates
 */
class Migration1591253089OrderDeeplinkForMailTemplatesTest extends TestCase
{
    use MigrationTestTrait;

    public function testNoDeDe(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('UPDATE locale SET code = "x-tst-TST" WHERE code = "de-DE"');

        // execute migration
        $migration = new Migration1591253089OrderDeeplinkForMailTemplates();
        $migration->update($connection);

        static::assertTrue(true);
    }

    public function testNoEnAndNoDe(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('UPDATE locale SET code = "x-tst-TST" WHERE code = "de-DE"');
        $connection->executeStatement('UPDATE locale SET code = "x-tst-TST2" WHERE code = "en-GB"');

        // execute migration
        $migration = new Migration1591253089OrderDeeplinkForMailTemplates();
        $migration->update($connection);

        static::assertTrue(true);
    }
}
