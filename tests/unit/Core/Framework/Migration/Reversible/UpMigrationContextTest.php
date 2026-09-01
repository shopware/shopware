<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpMigrationContext::class)]
class UpMigrationContextTest extends TestCase
{
    public function testExposesConnectionAndInstallationFlag(): void
    {
        $connection = static::createStub(Connection::class);

        $context = new UpMigrationContext($connection, true);

        static::assertSame($connection, $context->connection);
        static::assertTrue($context->isInstallation);

        static::assertFalse((new UpMigrationContext($connection, false))->isInstallation);
    }

    public function testIsFinalSoPluginsCannotSubclassIt(): void
    {
        // the class is passed into plugin code, so appending constructor parameters must stay safe
        static::assertTrue((new \ReflectionClass(UpMigrationContext::class))->isFinal());
    }
}
