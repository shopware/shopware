<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1674204177TaxProvider;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(Migration1674204177TaxProvider::class)]
class Migration1656657834TaxProviderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testTablesAreCreated(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('DROP TABLE IF EXISTS `tax_provider_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `tax_provider`');

        $migration = new Migration1674204177TaxProvider();
        $migration->update($connection);
        $migration->update($connection);

        static::assertNotFalse($connection->fetchFirstColumn('SELECT COUNT(*) FROM `tax_provider`'));
        static::assertNotFalse($connection->fetchFirstColumn('SELECT COUNT(*) FROM `tax_provider_translation`'));
    }
}
