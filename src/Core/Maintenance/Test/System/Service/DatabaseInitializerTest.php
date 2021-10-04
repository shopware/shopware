<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\System\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopware\Core\Maintenance\System\Service\DatabaseInitializer;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

class DatabaseInitializerTest extends TestCase
{
    public function testInitialize(): void
    {
        $connectionInfo = DatabaseConnectionInformation::fromEnv();

        $testDbName = 'test_schema';
        $connection = DatabaseConnectionFactory::createConnection($connectionInfo, true);
        $databaseInitializer = new DatabaseInitializer($connection);

        try {
            static::assertNotContains($testDbName, $databaseInitializer->getExistingDatabases([]));

            $databaseInitializer->createDatabase($testDbName);

            static::assertContains($testDbName, $databaseInitializer->getExistingDatabases([]));
            static::assertFalse($databaseInitializer->hasShopwareTables($testDbName));

            $databaseInitializer->initializeShopwareDb($testDbName);

            static::assertTrue($databaseInitializer->hasShopwareTables($testDbName));
        } finally {
            $databaseInitializer->dropDatabase($testDbName);

            static::assertNotContains($testDbName, $databaseInitializer->getExistingDatabases([]));
        }
    }
}
