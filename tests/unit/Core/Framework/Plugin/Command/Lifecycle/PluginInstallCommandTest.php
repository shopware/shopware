<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginInstallCommand;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(PluginInstallCommand::class)]
class PluginInstallCommandTest extends TestCase
{
    public function testInstallSortsPluginsByRequirements(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());

        $dependentPlugin = $this->createPlugin($projectDir, $filesystem, 'DependentPlugin', 'swag/dependent-plugin', ['swag/base-plugin']);
        $independentPlugin = $this->createPlugin($projectDir, $filesystem, 'IndependentPlugin', 'swag/independent-plugin');
        $basePlugin = $this->createPlugin($projectDir, $filesystem, 'BasePlugin', 'swag/base-plugin');

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([
            $dependentPlugin,
            $independentPlugin,
            $basePlugin,
        ])]);

        $installedPlugins = [];
        $installContext = $this->createMock(InstallContext::class);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects($this->exactly(3))
            ->method('installPlugin')
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$installedPlugins, $installContext): InstallContext {
                $installedPlugins[] = $plugin->getName();
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $installContext;
            });

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        try {
            $tester = new CommandTester($command);

            static::assertSame(Command::SUCCESS, $tester->execute([
                'plugins' => ['DependentPlugin', 'IndependentPlugin', 'BasePlugin'],
            ], ['interactive' => false]));

            static::assertSame(['BasePlugin', 'DependentPlugin', 'IndependentPlugin'], $installedPlugins);
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    public function testInstallFailsWhenPluginComposerJsonIsMissingDuringRequirementSorting(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $plugin = $this->createPluginEntity('MissingComposerPlugin', 'swag/missing-composer-plugin');
        $existingPlugin = $this->createPlugin($projectDir, $filesystem, 'ExistingComposerPlugin', 'swag/existing-composer-plugin');

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([$plugin, $existingPlugin])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->never())->method('installPlugin');

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        $tester = new CommandTester($command);

        try {
            $this->expectException(PluginException::class);
            $this->expectExceptionMessage(\sprintf('Plugin "MissingComposerPlugin" has no composer.json at "%s".', Path::join($projectDir, 'custom/plugins/MissingComposerPlugin/composer.json')));

            $tester->execute(['plugins' => ['MissingComposerPlugin', 'ExistingComposerPlugin']], ['interactive' => false]);
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    public function testInstallSkipsAlreadyInstalledPlugin(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $plugin = $this->createPlugin($projectDir, $filesystem, 'InstalledPlugin', 'swag/installed-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->never())->method('installPlugin');
        $pluginLifecycleService->expects($this->never())->method('activatePlugin');

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        try {
            $tester = new CommandTester($command);

            static::assertSame(Command::SUCCESS, $tester->execute([
                'plugins' => ['InstalledPlugin'],
            ], ['interactive' => false]));
            static::assertStringContainsString('Plugin "InstalledPlugin" is already installed. Skipping.', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    public function testInstallActivatesAlreadyInstalledInactivePlugin(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $plugin = $this->createPlugin($projectDir, $filesystem, 'InstalledPlugin', 'swag/installed-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $plugin->setActive(false);

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->never())->method('installPlugin');
        $pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturn($this->createMock(ActivateContext::class));

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        try {
            $tester = new CommandTester($command);

            static::assertSame(Command::SUCCESS, $tester->execute([
                'plugins' => ['InstalledPlugin'],
                '--activate' => true,
            ], ['interactive' => false]));
            static::assertStringContainsString('Plugin "InstalledPlugin" is already installed. Activating.', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    public function testInstallWithActivateInstallsAndActivatesWithoutRequirementValidation(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $plugin = $this->createPlugin($projectDir, $filesystem, 'NewPlugin', 'swag/new-plugin');

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): InstallContext {
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createMock(InstallContext::class);
            });
        $pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class), false, false)
            ->willReturn($this->createMock(ActivateContext::class));

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        try {
            $tester = new CommandTester($command);

            static::assertSame(Command::SUCCESS, $tester->execute([
                'plugins' => ['NewPlugin'],
                '--activate' => true,
            ], ['interactive' => false]));
            static::assertStringContainsString('Plugin "NewPlugin" has been installed and activated successfully.', $tester->getDisplay());
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    public function testInstallWithReinstallUninstallsBeforeInstalling(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $plugin = $this->createPlugin($projectDir, $filesystem, 'ReinstallPlugin', 'swag/reinstall-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $calls = [];

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects($this->once())
            ->method('uninstallPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$calls): UninstallContext {
                $calls[] = 'uninstall';
                $plugin->setInstalledAt(null);

                return $this->createMock(UninstallContext::class);
            });
        $pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$calls): InstallContext {
                $calls[] = 'install';
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createMock(InstallContext::class);
            });

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        try {
            $tester = new CommandTester($command);

            static::assertSame(Command::SUCCESS, $tester->execute([
                'plugins' => ['ReinstallPlugin'],
                '--reinstall' => true,
            ], ['interactive' => false]));
            static::assertSame(['uninstall', 'install'], $calls);
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    public function testInstallPassesSkipAssetBuildStateToLifecycleService(): void
    {
        $filesystem = new Filesystem();
        $projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $plugin = $this->createPlugin($projectDir, $filesystem, 'SkipAssetBuildPlugin', 'swag/skip-asset-build-plugin');

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
        $pluginRepository = new StaticEntityRepository([new PluginCollection([$plugin])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::callback(static fn (Context $context): bool => $context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING)))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): InstallContext {
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createMock(InstallContext::class);
            });

        $command = new PluginInstallCommand(
            $pluginLifecycleService,
            $pluginRepository,
            $this->createMock(CacheClearer::class),
            $projectDir
        );
        $command->setHelperSet(new HelperSet());

        try {
            $tester = new CommandTester($command);

            static::assertSame(Command::SUCCESS, $tester->execute([
                'plugins' => ['SkipAssetBuildPlugin'],
                '--skip-asset-build' => true,
            ], ['interactive' => false]));
        } finally {
            $filesystem->remove($projectDir);
        }
    }

    /**
     * @param list<string> $requirements
     */
    private function createPlugin(
        string $projectDir,
        Filesystem $filesystem,
        string $name,
        string $composerName,
        array $requirements = []
    ): PluginEntity {
        $pluginPath = 'custom/plugins/' . $name;
        $composerJson = [
            'name' => $composerName,
            'description' => 'Plugin install command test fixture',
            'version' => '1.0.0',
            'type' => 'shopware-platform-plugin',
            'extra' => [
                'shopware-plugin-class' => $name . '\\' . $name,
            ],
        ];
        if ($requirements !== []) {
            $composerJson['require'] = array_fill_keys($requirements, '*');
        }

        $filesystem->dumpFile(
            Path::join($projectDir, $pluginPath, 'composer.json'),
            json_encode($composerJson, \JSON_THROW_ON_ERROR)
        );

        return $this->createPluginEntity($name, $composerName);
    }

    private function createPluginEntity(string $name, string $composerName): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName($name);
        $plugin->setLabel($name);
        $plugin->setVersion('1.0.0');
        $plugin->setPath('custom/plugins/' . $name);
        $plugin->setComposerName($composerName);
        $plugin->setActive(false);
        $plugin->setManagedByComposer(false);

        return $plugin;
    }
}
