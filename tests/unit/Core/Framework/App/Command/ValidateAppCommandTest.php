<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\ValidateAppCommand;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
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
    private MockObject&ManifestValidator $manifestValidator;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->manifestValidator = $this->createMock(ManifestValidator::class);

        $this->commandTester = new CommandTester(new ValidateAppCommand(
            __DIR__ . '/_fixtures/validate-apps',
            $this->manifestValidator
        ));
    }

    #[TestDox('All apps in the app directory are validated when no name is given')]
    public function testValidatesAllApps(): void
    {
        $this->manifestValidator->expects($this->once())->method('validate')->willReturn(Result::ok());

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('all apps valid', $this->commandTester->getDisplay());
    }

    #[TestDox('A single app is validated by its folder name')]
    public function testValidatesSingleAppByName(): void
    {
        $this->manifestValidator->expects($this->once())->method('validate')->willReturn(Result::ok());

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['name' => 'ValidApp']));
        static::assertStringContainsString('app is valid', $this->commandTester->getDisplay());
    }

    #[TestDox('Validation errors are printed and fail the command')]
    public function testFailsWithValidationErrors(): void
    {
        $this->manifestValidator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(Result::failed([new MissingPermissionError(['product:read'])]));

        static::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        static::assertStringContainsString('The app "test" is invalid', $this->commandTester->getDisplay());
    }

    #[TestDox('An unknown app folder name fails the command')]
    public function testFailsForUnknownAppName(): void
    {
        $this->manifestValidator->expects($this->never())->method('validate');

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['name' => 'MissingApp']));
        static::assertStringContainsString('No app with name "MissingApp" found.', $this->commandTester->getDisplay());
    }
}
