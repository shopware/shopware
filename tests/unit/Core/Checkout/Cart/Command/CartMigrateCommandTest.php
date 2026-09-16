<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Command\CartMigrateCommand;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Redis\RedisStub;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartMigrateCommand::class)]
class CartMigrateCommandTest extends TestCase
{
    #[TestDox('A source storage other than redis or sql is rejected')]
    public function testThrowsOnInvalidSourceStorage(): void
    {
        $commandTester = new CommandTester($this->createCommand(new RedisStub()));

        $this->expectExceptionObject(CartException::cartMigrationInvalidSource('file', ['redis', 'sql']));

        $commandTester->execute(['from' => 'file']);
    }

    #[TestDox('Migrating from redis without a configured redis connection throws')]
    public function testThrowsWithoutRedisConnectionWhenMigratingFromRedis(): void
    {
        $commandTester = new CommandTester($this->createCommand(null));

        $this->expectExceptionObject(CartException::cartMigrationMissingRedisConnection());

        $commandTester->execute(['from' => 'redis']);
    }

    #[TestDox('Migrating from sql without a configured redis connection throws')]
    public function testThrowsWithoutRedisConnectionWhenMigratingFromSql(): void
    {
        $commandTester = new CommandTester($this->createCommand(null));

        $this->expectExceptionObject(CartException::cartMigrationMissingRedisConnection());

        $commandTester->execute(['from' => 'sql']);
    }

    #[TestDox('Migrating from redis succeeds early when redis holds no carts')]
    public function testMigratesNothingFromEmptyRedis(): void
    {
        $commandTester = new CommandTester($this->createCommand(new RedisStub()));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['from' => 'redis']));
        static::assertStringContainsString('No carts found in Redis', $commandTester->getDisplay());
    }

    #[TestDox('Migrating from sql succeeds early when the cart table is empty')]
    public function testMigratesNothingFromEmptySqlStorage(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('0');

        $commandTester = new CommandTester($this->createCommand(new RedisStub(), $connection));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['from' => 'sql']));
        static::assertStringContainsString('No carts found in SQL database', $commandTester->getDisplay());
    }

    #[TestDox('The url argument creates the redis connection through the factory')]
    public function testUrlArgumentCreatesRedisConnection(): void
    {
        $factory = $this->createMock(RedisConnectionFactory::class);
        $factory
            ->expects($this->once())
            ->method('create')
            ->with('redis://localhost:6379')
            ->willReturn(new RedisStub());

        $commandTester = new CommandTester($this->createCommand(null, null, $factory));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            'from' => 'redis',
            'url' => 'redis://localhost:6379',
        ]));
        static::assertStringContainsString('No carts found in Redis', $commandTester->getDisplay());
    }

    private function createCommand(
        ?RedisStub $redis,
        ?Connection $connection = null,
        ?RedisConnectionFactory $factory = null
    ): CartMigrateCommand {
        return new CartMigrateCommand(
            $redis,
            $connection ?? static::createStub(Connection::class),
            120,
            $factory ?? static::createStub(RedisConnectionFactory::class),
            static::createStub(CartCompressor::class),
            static::createStub(ClockInterface::class)
        );
    }
}
