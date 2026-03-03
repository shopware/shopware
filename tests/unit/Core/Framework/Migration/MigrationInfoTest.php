<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationInfo;

/**
 * @internal
 */
#[CoversClass(MigrationInfo::class)]
class MigrationInfoTest extends TestCase
{
    public function testReturnsNullWhenQueryThrows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('db failed'));

        $migrationInfo = new MigrationInfo($connection);

        static::assertNull($migrationInfo->getFirstMigrationDate());
    }

    public function testReturnsNullWhenValueCannotBeParsed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('definitely-not-a-date');

        $migrationInfo = new MigrationInfo($connection);

        static::assertNull($migrationInfo->getFirstMigrationDate());
    }

    public function testReturnsNullWhenValueIsNotAString(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1234);

        $migrationInfo = new MigrationInfo($connection);

        static::assertNull($migrationInfo->getFirstMigrationDate());
    }

    public function testReturnsNullWhenValueIsAnEmptyString(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('');

        $migrationInfo = new MigrationInfo($connection);

        static::assertNull($migrationInfo->getFirstMigrationDate());
    }

    public function testFormatsDateAsRfc3339ExtendedUtc(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('2020-01-01 00:00:00.123456');

        $migrationInfo = new MigrationInfo($connection);

        static::assertSame('2020-01-01T00:00:00.123+00:00', $migrationInfo->getFirstMigrationDate());
    }
}
