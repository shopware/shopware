<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\User\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Maintenance\User\Command\UserCreateCommand;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
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

    public function testPasswordMinLength(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.userPermission.passwordMinLength', 8);

        $commandTester = new CommandTester($this->getContainer()->get(UserCreateCommand::class));

        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage('The password length cannot be shorter than 8 characters.');

        $commandTester->setInputs(['short']);

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
    }
}
