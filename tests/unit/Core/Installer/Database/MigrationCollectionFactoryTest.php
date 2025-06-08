<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Core\TestBootstrapper;

/**
 * @internal
 */
#[CoversClass(MigrationCollectionFactory::class)]
class MigrationCollectionFactoryTest extends TestCase
{
    public function testGetMigrationCollectionLoader(): void
    {
        $factory = new MigrationCollectionFactory((new TestBootstrapper())->getProjectDir());
        $loader = $factory->getMigrationCollectionLoader(
            $this->createMock(Connection::class)
        );

        static::assertArrayHasKey('core', $loader->collectAll());
        static::assertArrayHasKey('core.V6_3', $loader->collectAll());
        static::assertArrayHasKey('core.V6_4', $loader->collectAll());
        static::assertArrayHasKey('core.V6_5', $loader->collectAll());
    }
}
