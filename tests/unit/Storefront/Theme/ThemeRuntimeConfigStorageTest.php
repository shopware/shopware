<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeRuntimeConfigStorage::class)]
class ThemeRuntimeConfigStorageTest extends TestCase
{
    public function testSaveRetriesOnDeadlock(): void
    {
        $attempts = 0;

        $connection = $this->createMock(Connection::class);
        // Not inside a transaction: RetryableQuery is allowed to retry the statement itself.
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function () use (&$attempts): int {
                ++$attempts;
                if ($attempts === 1) {
                    throw new DeadlockException(new PdoException('Deadlock found (1213)'), null);
                }

                return 1;
            });

        $storage = new ThemeRuntimeConfigStorage($connection);
        $storage->save($this->createConfig());

        static::assertSame(2, $attempts);
    }

    public function testSaveDoesNotRetryInsideTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        // Inside a surrounding transaction the deadlock already rolled it back, so RetryableQuery
        // must rethrow instead of retrying the single statement.
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willThrowException(new DeadlockException(new PdoException('Deadlock found (1213)'), null));

        $storage = new ThemeRuntimeConfigStorage($connection);

        $this->expectException(DeadlockException::class);
        $storage->save($this->createConfig());
    }

    public function testSaveDoesNotRetryUnrelatedDatabaseErrors(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willThrowException(new UniqueConstraintViolationException(new PdoException('Duplicate entry'), null));

        $storage = new ThemeRuntimeConfigStorage($connection);

        $this->expectException(UniqueConstraintViolationException::class);
        $storage->save($this->createConfig());
    }

    private function createConfig(): ThemeRuntimeConfig
    {
        return ThemeRuntimeConfig::fromArray([
            'themeId' => Uuid::randomHex(),
            'technicalName' => 'zenitPlatformGravity',
            'resolvedConfig' => ['key' => 'value'],
            'viewInheritance' => ['@Storefront'],
            'scriptFiles' => ['app.js'],
            'iconSets' => [],
            'importMap' => null,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }
}
