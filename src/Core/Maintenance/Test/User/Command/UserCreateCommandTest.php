<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\User\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Maintenance\User\Command\UserCreateCommand;
use Symfony\Component\Console\Tester\CommandTester;

class UserCreateCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TEST_USERNAME = 'shopware';

    public function testEmptyPasswordOption(): void
    {
        $commandTester = new CommandTester($this->getContainer()->get(UserCreateCommand::class));

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('The password cannot be empty');

        $commandTester->setInputs(['', '', '']);

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
    }
}
