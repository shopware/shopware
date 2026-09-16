<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Command\StoreLoginCommand;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('checkout')]
class StoreLoginCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testEmptyPasswordOption(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(StoreLoginCommand::class));

        $violations = Validation::createValidator()->validate('', new NotBlank(message: 'The password cannot be empty'));
        $this->expectExceptionObject(new ValidationFailedException('', $violations));

        $commandTester->setInputs(['', '', '']);
        $commandTester->execute([
            '--shopwareId' => 'no-reply@shopware.de',
            '--user' => 'missing_user',
        ]);
    }

    public function testValidPasswordOptionInvalidUserOption(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(StoreLoginCommand::class));

        $commandTester->setInputs(['non-empty-password']);
        $commandTester->execute([
            '--shopwareId' => 'no-reply@shopware.de',
            '--user' => 'missing_user',
        ]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('User not found', $commandTester->getDisplay());
    }
}
