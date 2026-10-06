<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\InstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InstallAppCommand::class)]
class InstallAppCommandTest extends TestCase
{
    private Stub&AppLoader $appLoader;

    private MockObject&AbstractAppLifecycle $appLifecycle;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->appLoader = static::createStub(AppLoader::class);
        $this->appLifecycle = $this->createMock(AbstractAppLifecycle::class);

        $this->commandTester = new CommandTester(new InstallAppCommand(
            $this->appLoader,
            $this->appLifecycle,
            static::createStub(AppPrinter::class)
        ));
    }

    #[TestDox('A matching app is validated and installed with the activate flag')]
    public function testInstallsMatchingApp(): void
    {
        $manifest = $this->createManifest();
        $this->appLoader->method('load')->willReturn(['test' => $manifest]);
        $this->appLifecycle
            ->expects($this->once())
            ->method('install')
            ->willReturnCallback(function (Manifest $installed, AppInstallParameters $parameters, Context $context) use ($manifest): void {
                $this->assertSame($manifest, $installed);
                $this->assertTrue($parameters->activate);
                $this->assertTrue($parameters->acceptPermissions);
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'name' => ['test'],
            '--force' => true,
            '--activate' => true,
        ]));
        static::assertStringContainsString('App test has been successfully installed.', $this->commandTester->getDisplay());
    }

    #[TestDox('An unknown app name reports that no app was found')]
    public function testReportsUnknownApp(): void
    {
        $this->appLoader->method('load')->willReturn([]);
        $this->appLifecycle->expects($this->never())->method('install');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'name' => ['unknown'],
        ]));
        static::assertStringContainsString('Could not find any app with this name', $this->commandTester->getDisplay());
    }

    #[TestDox('An already installed app is skipped with a notice')]
    public function testSkipsAlreadyInstalledApp(): void
    {
        $this->appLoader->method('load')->willReturn(['test' => $this->createManifest()]);
        $this->appLifecycle
            ->expects($this->once())
            ->method('install')
            ->willThrowException(AppException::alreadyInstalled('test'));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'name' => ['test'],
            '--force' => true,
        ]));
        static::assertStringContainsString('App test is already installed', $this->commandTester->getDisplay());
    }

    #[TestDox('A refused installation is reported and fails the command')]
    public function testReportsARefusedInstallation(): void
    {
        $this->appLoader->method('load')->willReturn(['test' => $this->createManifest()]);
        $this->appLifecycle
            ->expects($this->once())
            ->method('install')
            ->willThrowException(AppException::validationFailedFromError(new MissingPermissionError(['product:read'])));

        static::assertSame(Command::FAILURE, $this->commandTester->execute([
            'name' => ['test'],
            '--force' => true,
        ]));
        static::assertStringContainsString('App installation of test failed due', $this->commandTester->getDisplay());
        static::assertStringContainsString('product:read', $this->commandTester->getDisplay());
    }

    #[TestDox('One refused app does not stop the others from installing')]
    public function testARefusedAppDoesNotStopTheRest(): void
    {
        $this->appLoader->method('load')->willReturn([
            'refused' => $this->createManifest('refused'),
            'fine' => $this->createManifest('fine'),
        ]);

        $installed = [];
        $this->appLifecycle
            ->expects($this->exactly(2))
            ->method('install')
            ->willReturnCallback(function (Manifest $manifest) use (&$installed): void {
                if ($manifest->getMetadata()->getName() === 'refused') {
                    throw AppException::validationFailedFromError(new MissingPermissionError(['product:read']));
                }

                $installed[] = $manifest->getMetadata()->getName();
            });

        static::assertSame(Command::FAILURE, $this->commandTester->execute([
            'name' => ['refused', 'fine'],
            '--force' => true,
        ]));
        static::assertSame(['fine'], $installed);
    }

    private function createManifest(?string $name = null): Manifest
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        if ($name !== null) {
            $manifest->getMetadata()->assign(['name' => $name]);
        }

        return $manifest;
    }
}
