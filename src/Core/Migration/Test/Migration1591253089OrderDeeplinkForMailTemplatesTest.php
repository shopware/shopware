<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1591253089OrderDeeplinkForMailTemplates;

class Migration1591253089OrderDeeplinkForMailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoDeDe(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeUpdate('UPDATE locale SET code = "x-tst-TST" WHERE code = "de-DE"');

        // execute migration
        $migration = new Migration1591253089OrderDeeplinkForMailTemplates();
        $migration->update($connection);
    }

    public function testNoEnAndNoDe(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeUpdate('UPDATE locale SET code = "x-tst-TST" WHERE code = "de-DE"');
        $connection->executeUpdate('UPDATE locale SET code = "x-tst-TST2" WHERE code = "en-GB"');

        // execute migration
        $migration = new Migration1591253089OrderDeeplinkForMailTemplates();
        $migration->update($connection);
    }
}
