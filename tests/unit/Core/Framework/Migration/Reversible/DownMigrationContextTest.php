<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DownMigrationContext::class)]
class DownMigrationContextTest extends TestCase
{
    public function testExposesConnectionAndKeepUserDataFlag(): void
    {
        $connection = static::createStub(Connection::class);

        $context = new DownMigrationContext($connection, true);

        static::assertSame($connection, $context->connection);
        static::assertTrue($context->keepUserData);

        static::assertFalse((new DownMigrationContext($connection, false))->keepUserData);
    }

    public function testIsFinalSoPluginsCannotSubclassIt(): void
    {
        // the class is passed into plugin code, so appending constructor parameters must stay safe
        static::assertTrue((new \ReflectionClass(DownMigrationContext::class))->isFinal());
    }
}
