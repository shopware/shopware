<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\RefreshAppCommand;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Result;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RefreshAppCommand::class)]
class RefreshAppCommandTest extends TestCase
{
    private MockObject&AppService $appService;

    private MockObject&AppPrinter $appPrinter;

    private MockObject&ManifestValidator $manifestValidator;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->appService = $this->createMock(AppService::class);
        $this->appPrinter = $this->createMock(AppPrinter::class);
        $this->manifestValidator = $this->createMock(ManifestValidator::class);

        $this->commandTester = new CommandTester(new RefreshAppCommand(
            $this->appService,
            $this->appPrinter,
            $this->manifestValidator
        ));
    }

    #[TestDox('Nothing is refreshed when no app changed')]
    public function testNotesWhenNothingIsRefreshable(): void
    {
        $this->appService->method('getRefreshableAppInfo')->willReturn(new RefreshableAppDryRun());
        $this->appService->expects($this->never())->method('doRefreshApps');
        $this->manifestValidator->expects($this->never())->method('validate');
        $this->appPrinter->expects($this->never())->method('printInstalledApps');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('Nothing to install, update or delete.', $this->commandTester->getDisplay());
    }

    #[TestDox('Refreshable apps are validated and refreshed, the result is printed')]
    public function testRefreshesValidApps(): void
    {
        $this->appService->method('getRefreshableAppInfo')->willReturn($this->createDryRunWithInstallableApp());
        $this->manifestValidator->expects($this->once())->method('validate')->willReturn(Result::ok());
        $this->appService
            ->expects($this->once())
            ->method('doRefreshApps')
            ->willReturnCallback(function (AppInstallParameters $parameters, Context $context, array $appNames): array {
                $this->assertTrue($parameters->activate);
                $this->assertSame(['test'], $appNames);

                return [];
            });
        $this->appPrinter->expects($this->once())->method('printInstalledApps');
        $this->appPrinter->expects($this->once())->method('printIncompleteInstallations')->with(static::anything(), []);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            '--force' => true,
            '--activate' => true,
        ]));
        static::assertStringContainsString('all refreshable apps are valid', $this->commandTester->getDisplay());
    }

    #[TestDox('Validation errors prevent the refresh and fail the command')]
    public function testFailsOnValidationError(): void
    {
        $this->appService->method('getRefreshableAppInfo')->willReturn($this->createDryRunWithInstallableApp());
        $this->manifestValidator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(Result::failed([new MissingPermissionError(['product:read'])]));
        $this->appService->expects($this->never())->method('doRefreshApps');
        $this->appPrinter->expects($this->never())->method('printInstalledApps');

        static::assertSame(Command::FAILURE, $this->commandTester->execute([
            '--force' => true,
        ]));
        static::assertStringContainsString('The app "test" is invalid', $this->commandTester->getDisplay());
    }

    #[TestDox('Declining the permission summary aborts the refresh')]
    public function testAbortsWhenPermissionsAreDeclined(): void
    {
        $this->appService->method('getRefreshableAppInfo')->willReturn($this->createDryRunWithInstallableApp());
        $this->appService->expects($this->never())->method('doRefreshApps');
        $this->manifestValidator->expects($this->never())->method('validate');
        $this->appPrinter->expects($this->never())->method('printInstalledApps');

        $this->commandTester->setInputs(['no']);

        static::assertSame(Command::FAILURE, $this->commandTester->execute([]));
        static::assertStringContainsString('Aborting due to user input.', $this->commandTester->getDisplay());
    }

    private function createDryRunWithInstallableApp(): RefreshableAppDryRun
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $dryRun = new RefreshableAppDryRun();
        $dryRun->install($manifest, new AppInstallParameters(), Context::createDefaultContext());

        return $dryRun;
    }
}
