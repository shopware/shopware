<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\User\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\MaintenanceException;
use Shopware\Core\Maintenance\User\Command\UserCreateCommand;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(UserCreateCommand::class)]
class UserCreateCommandTest extends TestCase
{
    private const TEST_USERNAME = 'shopware';

    public function testEmptyPasswordOption(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
        $output = $commandTester->getDisplay();
        static::assertStringContainsString('[WARNING] You didn\'t pass a password so a random one was generated.', $output);
        static::assertStringContainsString('[OK] User "shopware" successfully created. The newly generated password is:', $output);
    }

    public function testPasswordMinLength(): void
    {
        $commandTester = $this->getCommandTester();

        $this->expectException(MaintenanceException::class);
        $this->expectExceptionMessage('The password must have at least 8 characters.');

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => 'short',
        ]);
    }

    private function getCommandTester(): CommandTester
    {
        return new CommandTester(new UserCreateCommand(new UserProvisioner($this->createConnection())));
    }

    private function createConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $builder = $this->createMock(QueryBuilder::class);
        $builder->method('select')->willReturnSelf();
        $builder->method('from')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('innerJoin')->willReturnSelf();
        $builder->method('setParameter')->willReturnSelf();
        $connection->method('createQueryBuilder')->willReturn($builder);

        $connection->method('fetchOne')->willReturn('{"_value": 8}');

        return $connection;
    }
}
