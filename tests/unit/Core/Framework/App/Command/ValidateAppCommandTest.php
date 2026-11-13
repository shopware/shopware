<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\ValidateAppCommand;
use Shopware\Core\Framework\App\Validation\AppValidator;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Result;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ValidateAppCommand::class)]
class ValidateAppCommandTest extends TestCase
{
    private MockObject&AppValidator $appValidator;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->appValidator = $this->createMock(AppValidator::class);

        $this->commandTester = new CommandTester(new ValidateAppCommand(
            __DIR__ . '/_fixtures/validate-apps',
            $this->appValidator
        ));
    }

    #[TestDox('All apps in the app directory are validated when no name is given')]
    public function testValidatesAllApps(): void
    {
        $this->appValidator->expects($this->once())->method('validate')->willReturn(Result::ok());

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertSame('✔ 1 app valid', trim($this->commandTester->getDisplay()));
    }

    #[TestDox('A single app is validated by its folder name')]
    public function testValidatesSingleAppByName(): void
    {
        $this->appValidator->expects($this->once())->method('validate')->willReturn(Result::ok());

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => 'ValidApp']));
        static::assertSame('✔ 1 app valid', trim($this->commandTester->getDisplay()));
    }

    #[TestDox('Errors are listed under the app and fail the command')]
    public function testFailsWithValidationErrors(): void
    {
        $this->appValidator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(Result::failed([new MissingPermissionError(['product:read'])]));

        static::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        static::assertSame(
            "test\n  error   Missing permissions: product:read\n\n✖ 1 error, 0 notices (1 of 1 app invalid)",
            trim($this->commandTester->getDisplay())
        );
    }

    #[TestDox('Notices are listed under the app but do not fail the command')]
    public function testNoticesDoNotFailTheCommand(): void
    {
        $this->appValidator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(Result::failed([new MissingTranslationError(['de-DE'])]));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertSame(
            "test\n  notice  Missing label translations: de-DE\n\n✔ 0 errors, 1 notice",
            trim($this->commandTester->getDisplay())
        );
    }

    #[TestDox('An unknown app folder name fails the command')]
    public function testFailsForUnknownAppName(): void
    {
        $this->appValidator->expects($this->never())->method('validate');

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['name' => 'MissingApp']));
        static::assertStringContainsString('No app with name "MissingApp" found.', $this->commandTester->getDisplay());
    }
}
