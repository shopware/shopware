<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\ConsoleCommandProcessRunner;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginInstallCommand;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginComposerDependencyService;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginInstallCommand::class)]
class PluginInstallCommandTest extends TestCase
{
    private string $projectDir;

    private MockObject&PluginLifecycleService $pluginLifecycleService;

    private CacheClearer&Stub $cacheClearer;

    private PluginCollection $plugins;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->projectDir = __DIR__ . '/_fixtures/project';
        $this->pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $this->cacheClearer = static::createStub(CacheClearer::class);
        $this->plugins = new PluginCollection();

        $pluginRepository = new StaticEntityRepository([
            fn (Criteria $criteria, Context $context): PluginCollection => $this->plugins,
        ]);

        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            $pluginRepository,
            $this->cacheClearer,
            $this->projectDir
        );
        $command->setHelperSet(new HelperSet());

        $this->commandTester = new CommandTester($command);
    }

    public function testInstallSortsPluginsByRequirements(): void
    {
        $dependentPlugin = $this->createPluginEntity('DependentPlugin', 'swag/dependent-plugin');
        $independentPlugin = $this->createPluginEntity('IndependentPlugin', 'swag/independent-plugin');
        $basePlugin = $this->createPluginEntity('BasePlugin', 'swag/base-plugin');
        $this->plugins->fill([$dependentPlugin, $independentPlugin, $basePlugin]);

        $installedPlugins = [];
        $installContext = static::createStub(InstallContext::class);

        $this->pluginLifecycleService
            ->expects($this->exactly(3))
            ->method('installPlugin')
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$installedPlugins, $installContext): InstallContext {
                $installedPlugins[] = $plugin->getName();
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $installContext;
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['DependentPlugin', 'IndependentPlugin', 'BasePlugin'],
        ], ['interactive' => false]));

        static::assertSame(['BasePlugin', 'DependentPlugin', 'IndependentPlugin'], $installedPlugins);
    }

    public function testInstallSortsStorePluginBeforePluginRequiringItsStoreAlias(): void
    {
        $dependentPlugin = $this->createPluginEntity('StoreDependentPlugin', 'a-vendor/store-dependent-plugin');
        $storePlugin = $this->createPluginEntity('StoreDependencyPlugin', 'store.shopware.com/store-dependency-plugin');
        $this->plugins->fill([$dependentPlugin, $storePlugin]);

        $installedPlugins = [];
        $installContext = static::createStub(InstallContext::class);

        $this->pluginLifecycleService
            ->expects($this->exactly(2))
            ->method('installPlugin')
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$installedPlugins, $installContext): InstallContext {
                $installedPlugins[] = $plugin->getName();
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $installContext;
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['StoreDependentPlugin', 'StoreDependencyPlugin'],
        ], ['interactive' => false]));

        static::assertSame(['StoreDependencyPlugin', 'StoreDependentPlugin'], $installedPlugins);
    }

    public function testInstallFailsWhenPluginComposerJsonIsMissingDuringRequirementSorting(): void
    {
        $missingPlugin = $this->createPluginEntity('MissingComposerPlugin', 'swag/missing-composer-plugin');
        $existingPlugin = $this->createPluginEntity('ExistingComposerPlugin', 'swag/existing-composer-plugin');
        $this->plugins->fill([$missingPlugin, $existingPlugin]);

        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');

        $this->expectExceptionObject(PluginException::composerJsonMissing('MissingComposerPlugin', Path::join($this->projectDir, 'plugins/MissingComposerPlugin/composer.json')));

        $this->commandTester->execute(['plugins' => ['MissingComposerPlugin', 'ExistingComposerPlugin']], ['interactive' => false]);
    }

    public function testInstallSkipsAlreadyInstalledPlugin(): void
    {
        $plugin = $this->createPluginEntity('InstalledPlugin', 'swag/installed-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $this->plugins->add($plugin);

        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $this->pluginLifecycleService->expects($this->never())->method('activatePlugin');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['InstalledPlugin'],
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "InstalledPlugin" is already installed. Skipping.', $this->commandTester->getDisplay());
    }

    public function testInstallActivatesAlreadyInstalledInactivePlugin(): void
    {
        $plugin = $this->createPluginEntity('InstalledPlugin', 'swag/installed-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $plugin->setActive(false);
        $this->plugins->add($plugin);

        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturn(static::createStub(ActivateContext::class));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['InstalledPlugin'],
            '--activate' => true,
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "InstalledPlugin" is already installed. Activating.', $this->commandTester->getDisplay());
    }

    public function testInstallWithActivateInstallsAndActivatesWithoutRequirementValidation(): void
    {
        $plugin = $this->createPluginEntity('NewPlugin', 'swag/new-plugin');
        $this->plugins->add($plugin);

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): InstallContext {
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createStub(InstallContext::class);
            });
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class), false, false)
            ->willReturn(static::createStub(ActivateContext::class));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['NewPlugin'],
            '--activate' => true,
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "NewPlugin" has been installed and activated successfully.', $this->commandTester->getDisplay());
    }

    public function testInstallWithReinstallUninstallsBeforeInstalling(): void
    {
        $plugin = $this->createPluginEntity('ReinstallPlugin', 'swag/reinstall-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $this->plugins->add($plugin);

        $calls = [];

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('uninstallPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$calls): UninstallContext {
                $calls[] = 'uninstall';
                $plugin->setInstalledAt(null);

                return $this->createStub(UninstallContext::class);
            });
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$calls): InstallContext {
                $calls[] = 'install';
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createStub(InstallContext::class);
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['ReinstallPlugin'],
            '--reinstall' => true,
        ], ['interactive' => false]));
        static::assertSame(['uninstall', 'install'], $calls);
    }

    public function testInstallPassesSkipAssetBuildStateToLifecycleService(): void
    {
        $plugin = $this->createPluginEntity('SkipAssetBuildPlugin', 'swag/skip-asset-build-plugin');
        $this->plugins->add($plugin);

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::callback(static fn (Context $context): bool => $context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING)))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): InstallContext {
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createStub(InstallContext::class);
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['SkipAssetBuildPlugin'],
            '--skip-asset-build' => true,
        ], ['interactive' => false]));
    }

    public function testRequiresThePackageBeforeTheInstall(): void
    {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $this->plugins->add($plugin);

        $callOrder = [];
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->willReturnCallback(static function () use (&$callOrder): bool {
            $callOrder[] = 'composer require';

            return false;
        });
        $composerDependencyService->expects($this->never())->method('removeDependency');
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->willReturnCallback(static function (PluginEntity $plugin, Context $context) use (&$callOrder): InstallContext {
                static::assertTrue($context->hasState(PluginLifecycleService::STATE_SEPARATE_COMPOSER_STEP));
                $callOrder[] = 'install';

                return static::createStub(InstallContext::class);
            });

        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            new StaticEntityRepository([fn (): PluginCollection => $this->plugins]),
            $this->cacheClearer,
            $this->projectDir,
            $composerDependencyService,
        );
        $command->setHelperSet(new HelperSet());

        (new CommandTester($command))->execute(['plugins' => ['SwagFirst']], ['interactive' => false]);

        static::assertSame(['composer require', 'install'], $callOrder);
    }

    public function testRunsTheInstallInAProcessOfItsOwnWhenAPackageWasRequired(): void
    {
        $this->plugins->add($this->createPluginEntity('SwagFirst', 'swag/first'));

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->willReturn(true);
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner
            ->expects($this->once())
            ->method('run')
            ->with(
                static::isInstanceOf(PluginInstallCommand::class),
                static::isInstanceOf(InputInterface::class),
                ['SwagFirst'],
                static::isInstanceOf(OutputInterface::class),
            )
            ->willReturn(0);

        $commandTester = new CommandTester($this->createCommand($composerDependencyService, $commandRunner));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['plugins' => ['Swag']], ['interactive' => false]));
    }

    public function testRequiresThePackagesOfAllPluginsBeforeTheInstallRunsInAProcessOfItsOwn(): void
    {
        $this->plugins->fill([
            $this->createPluginEntity('IndependentPlugin', 'swag/independent-plugin'),
            $this->createPluginEntity('BasePlugin', 'swag/base-plugin'),
        ]);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->exactly(2))->method('requireDependency')->willReturnOnConsecutiveCalls(true, false);
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->once())->method('run')->with(static::anything(), static::anything(), ['BasePlugin', 'IndependentPlugin'])->willReturn(0);

        $commandTester = new CommandTester($this->createCommand($composerDependencyService, $commandRunner));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['plugins' => ['BasePlugin', 'IndependentPlugin']], ['interactive' => false]));
    }

    public function testInstallsInTheSameProcessWhenNoPackageWasRequired(): void
    {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $this->plugins->add($plugin);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->willReturn(false);
        $this->pluginLifecycleService->expects($this->once())->method('installPlugin')->with($plugin)->willReturn(static::createStub(InstallContext::class));
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->never())->method('run');

        $commandTester = new CommandTester($this->createCommand($composerDependencyService, $commandRunner));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['plugins' => ['SwagFirst']], ['interactive' => false]));
    }

    public function testDoesNotRequireThePackageOfAnInstalledPlugin(): void
    {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $this->plugins->add($plugin);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->never())->method('requireDependency');
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->never())->method('run');

        $commandTester = new CommandTester($this->createCommand($composerDependencyService, $commandRunner));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['plugins' => ['SwagFirst']], ['interactive' => false]));
    }

    public function testRequiresThePackageOfAnInstalledPluginWhenItIsReinstalled(): void
    {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $this->plugins->add($plugin);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->with($plugin)->willReturn(true);
        $this->pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->once())->method('run')->willReturn(0);

        $commandTester = new CommandTester($this->createCommand($composerDependencyService, $commandRunner));

        $commandTester->execute(['plugins' => ['SwagFirst'], '--reinstall' => true], ['interactive' => false]);
    }

    #[DataProvider('provideFailedProcesses')]
    public function testRemovesTheRequiredPackageOfAPluginThatIsNotInstalledWhenTheProcessFails(
        int $exitCode,
        bool $isInstalledAfterwards,
        int $expectedRemovals,
    ): void {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $this->plugins->add($plugin);
        $pluginAfterwards = clone $plugin;
        $pluginAfterwards->setInstalledAt($isInstalledAfterwards ? new \DateTimeImmutable() : null);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->method('requireDependency')->willReturn(true);
        $composerDependencyService
            ->expects($this->exactly($expectedRemovals))
            ->method('removePackage')
            ->with($pluginAfterwards, static::isInstanceOf(Context::class));
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $commandRunner = static::createStub(ConsoleCommandProcessRunner::class);
        $commandRunner->method('run')->willReturn($exitCode);

        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            new StaticEntityRepository([fn (): PluginCollection => $this->plugins, new PluginCollection([$pluginAfterwards])]),
            $this->cacheClearer,
            $this->projectDir,
            $composerDependencyService,
            $commandRunner,
        );
        $command->setHelperSet(new HelperSet());

        static::assertSame($exitCode, (new CommandTester($command))->execute(['plugins' => ['SwagFirst']], ['interactive' => false]));
    }

    /**
     * @return iterable<string, array{int, bool, int}>
     */
    public static function provideFailedProcesses(): iterable
    {
        yield 'process failed before the plugin was installed' => [1, false, 1];
        yield 'process failed after the plugin was installed' => [1, true, 0];
        yield 'process succeeded' => [0, true, 0];
    }

    public function testRemovesThePackageOfAPluginRequiredEarlierWhenALaterRequireFails(): void
    {
        $basePlugin = $this->createPluginEntity('BasePlugin', 'swag/base-plugin');
        $independentPlugin = $this->createPluginEntity('IndependentPlugin', 'swag/independent-plugin');
        $this->plugins->fill([$basePlugin, $independentPlugin]);

        $requireFailure = new \RuntimeException('require failed');
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService
            ->expects($this->exactly(2))
            ->method('requireDependency')
            ->willReturnCallback(static fn (PluginEntity $plugin): bool => $plugin->getName() === 'BasePlugin' ? true : throw $requireFailure);
        $composerDependencyService
            ->expects($this->once())
            ->method('removePackage')
            ->with($basePlugin, static::isInstanceOf(Context::class));
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->never())->method('run');

        $commandTester = new CommandTester($this->createCommandWithPluginsNotInstalledAfterwards($composerDependencyService, $commandRunner, [$basePlugin]));

        try {
            $commandTester->execute(['plugins' => ['BasePlugin', 'IndependentPlugin']], ['interactive' => false]);
            static::fail('The failed require must be thrown again');
        } catch (\RuntimeException $exception) {
            static::assertSame($requireFailure, $exception);
        }

        static::assertStringContainsString(
            'Removed the Composer packages of the plugins that are not installed: BasePlugin',
            $this->getDisplayOnOneLine($commandTester)
        );
    }

    public function testRethrowsTheOriginalErrorAndRemovesTheOtherPackagesWhenARemovalFails(): void
    {
        $basePlugin = $this->createPluginEntity('BasePlugin', 'swag/base-plugin');
        $dependentPlugin = $this->createPluginEntity('DependentPlugin', 'swag/dependent-plugin');
        $independentPlugin = $this->createPluginEntity('IndependentPlugin', 'swag/independent-plugin');
        $this->plugins->fill([$basePlugin, $dependentPlugin, $independentPlugin]);

        $requireFailure = new \RuntimeException('require failed');
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService
            ->method('requireDependency')
            ->willReturnCallback(static fn (PluginEntity $plugin): bool => $plugin->getName() === 'IndependentPlugin' ? throw $requireFailure : true);
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $removedPluginNames = [];
        $composerDependencyService
            ->expects($this->exactly(2))
            ->method('removePackage')
            ->willReturnCallback(static function (PluginEntity $plugin) use (&$removedPluginNames): void {
                if ($plugin->getName() === 'BasePlugin') {
                    throw new \RuntimeException('removal failed');
                }

                $removedPluginNames[] = $plugin->getName();
            });
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->never())->method('run');

        $commandTester = new CommandTester($this->createCommandWithPluginsNotInstalledAfterwards(
            $composerDependencyService,
            $commandRunner,
            [$basePlugin, $dependentPlugin]
        ));

        try {
            $commandTester->execute(['plugins' => ['BasePlugin', 'DependentPlugin', 'IndependentPlugin']], ['interactive' => false]);
            static::fail('The failed require must be thrown again');
        } catch (\RuntimeException $exception) {
            static::assertSame($requireFailure, $exception);
        }

        static::assertSame(['DependentPlugin'], $removedPluginNames);
        static::assertStringContainsString(
            'Could not remove the Composer package of plugin "BasePlugin": removal failed',
            $this->getDisplayOnOneLine($commandTester)
        );
    }

    public function testReturnsTheExitCodeOfTheProcessWhenARemovalFails(): void
    {
        $basePlugin = $this->createPluginEntity('BasePlugin', 'swag/base-plugin');
        $independentPlugin = $this->createPluginEntity('IndependentPlugin', 'swag/independent-plugin');
        $this->plugins->fill([$basePlugin, $independentPlugin]);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->method('requireDependency')->willReturn(true);
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $removedPluginNames = [];
        $composerDependencyService
            ->expects($this->exactly(2))
            ->method('removePackage')
            ->willReturnCallback(static function (PluginEntity $plugin) use (&$removedPluginNames): void {
                if ($plugin->getName() === 'BasePlugin') {
                    throw new \RuntimeException('removal failed');
                }

                $removedPluginNames[] = $plugin->getName();
            });
        $commandRunner = static::createStub(ConsoleCommandProcessRunner::class);
        $commandRunner->method('run')->willReturn(1);

        $commandTester = new CommandTester($this->createCommandWithPluginsNotInstalledAfterwards(
            $composerDependencyService,
            $commandRunner,
            [$basePlugin, $independentPlugin]
        ));

        static::assertSame(1, $commandTester->execute(['plugins' => ['BasePlugin', 'IndependentPlugin']], ['interactive' => false]));
        static::assertSame(['IndependentPlugin'], $removedPluginNames);
        static::assertStringContainsString(
            'Could not remove the Composer package of plugin "BasePlugin": removal failed',
            $this->getDisplayOnOneLine($commandTester)
        );
    }

    public function testDoesNotSearchForPluginsToRollBackWhenTheFirstRequireFails(): void
    {
        $this->plugins->add($this->createPluginEntity('SwagFirst', 'swag/first'));

        $requireFailure = new \RuntimeException('require failed');
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->willThrowException($requireFailure);
        $composerDependencyService->expects($this->never())->method('removePackage');
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $pluginRepository = new StaticEntityRepository([fn (): PluginCollection => $this->plugins]);

        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            $pluginRepository,
            $this->cacheClearer,
            $this->projectDir,
            $composerDependencyService,
            static::createStub(ConsoleCommandProcessRunner::class),
        );
        $command->setHelperSet(new HelperSet());

        try {
            (new CommandTester($command))->execute(['plugins' => ['SwagFirst']], ['interactive' => false]);
            static::fail('The failed require must be thrown again');
        } catch (\RuntimeException $exception) {
            static::assertSame($requireFailure, $exception);
        }

        // only the search for the plugins named in the arguments ran
        static::assertCount(1, $pluginRepository->searchedCriteria);
    }

    public function testRemovesTheRequiredPackageWhenTheProcessCannotBeStarted(): void
    {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $this->plugins->add($plugin);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->method('requireDependency')->willReturn(true);
        $composerDependencyService
            ->expects($this->once())
            ->method('removePackage')
            ->with($plugin, static::isInstanceOf(Context::class));
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $startFailure = PluginException::phpBinaryNotFound('plugin:install');
        $commandRunner = static::createStub(ConsoleCommandProcessRunner::class);
        $commandRunner->method('run')->willThrowException($startFailure);

        $commandTester = new CommandTester($this->createCommandWithPluginsNotInstalledAfterwards($composerDependencyService, $commandRunner, [$plugin]));

        try {
            $commandTester->execute(['plugins' => ['SwagFirst']], ['interactive' => false]);
            static::fail('The failure to start the process must be thrown again');
        } catch (PluginException $exception) {
            static::assertSame($startFailure, $exception);
        }
    }

    public function testRemovesOnlyThePackagesThatWereRequiredWhenTheProcessFails(): void
    {
        $basePlugin = $this->createPluginEntity('BasePlugin', 'swag/base-plugin');
        $independentPlugin = $this->createPluginEntity('IndependentPlugin', 'swag/independent-plugin');
        $this->plugins->fill([$basePlugin, $independentPlugin]);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService
            ->method('requireDependency')
            ->willReturnCallback(static fn (PluginEntity $plugin): bool => $plugin->getName() === 'BasePlugin');
        $composerDependencyService
            ->expects($this->once())
            ->method('removePackage')
            ->with($basePlugin, static::isInstanceOf(Context::class));
        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $commandRunner = static::createStub(ConsoleCommandProcessRunner::class);
        $commandRunner->method('run')->willReturn(1);

        $commandTester = new CommandTester($this->createCommandWithPluginsNotInstalledAfterwards($composerDependencyService, $commandRunner, [$basePlugin]));

        static::assertSame(1, $commandTester->execute(['plugins' => ['BasePlugin', 'IndependentPlugin']], ['interactive' => false]));
    }

    public function testKeepsAPackageThatWasInstalledBeforeWhenTheInstallFails(): void
    {
        $plugin = $this->createPluginEntity('SwagFirst', 'swag/first');
        $this->plugins->add($plugin);

        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->method('requireDependency')->willReturn(false);
        $composerDependencyService->expects($this->never())->method('removePackage');
        $installFailure = new \RuntimeException('install failed');
        $this->pluginLifecycleService->expects($this->once())->method('installPlugin')->willThrowException($installFailure);

        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            new StaticEntityRepository([fn (): PluginCollection => $this->plugins]),
            $this->cacheClearer,
            $this->projectDir,
            $composerDependencyService,
        );
        $command->setHelperSet(new HelperSet());

        $this->expectExceptionObject($installFailure);

        (new CommandTester($command))->execute(['plugins' => ['SwagFirst']], ['interactive' => false]);
    }

    /**
     * @param list<PluginEntity> $pluginsWithRequiredPackage
     */
    private function createCommandWithPluginsNotInstalledAfterwards(
        PluginComposerDependencyService $composerDependencyService,
        ConsoleCommandProcessRunner $commandRunner,
        array $pluginsWithRequiredPackage,
    ): PluginInstallCommand {
        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            new StaticEntityRepository([
                fn (): PluginCollection => $this->plugins,
                static function (Criteria $criteria) use ($pluginsWithRequiredPackage): PluginCollection {
                    static::assertEqualsCanonicalizing(
                        array_map(static fn (PluginEntity $plugin): string => $plugin->getId(), $pluginsWithRequiredPackage),
                        array_values($criteria->getIds()),
                    );

                    return new PluginCollection($pluginsWithRequiredPackage);
                },
            ]),
            $this->cacheClearer,
            $this->projectDir,
            $composerDependencyService,
            $commandRunner,
        );
        $command->setHelperSet(new HelperSet());

        return $command;
    }

    private function getDisplayOnOneLine(CommandTester $commandTester): string
    {
        return (string) preg_replace('/\s+(?:!\s+)?/', ' ', $commandTester->getDisplay());
    }

    private function createCommand(PluginComposerDependencyService $composerDependencyService, ConsoleCommandProcessRunner $commandRunner): PluginInstallCommand
    {
        $command = new PluginInstallCommand(
            $this->pluginLifecycleService,
            new StaticEntityRepository([fn (): PluginCollection => $this->plugins]),
            $this->cacheClearer,
            $this->projectDir,
            $composerDependencyService,
            $commandRunner,
        );
        $command->setHelperSet(new HelperSet());

        return $command;
    }

    private function createPluginEntity(string $name, string $composerName): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName($name);
        $plugin->setLabel($name);
        $plugin->setVersion('1.0.0');
        $plugin->setPath('plugins/' . $name);
        $plugin->setComposerName($composerName);
        $plugin->setActive(false);
        $plugin->setManagedByComposer(false);

        return $plugin;
    }
}
