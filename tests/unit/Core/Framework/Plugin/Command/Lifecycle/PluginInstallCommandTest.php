<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    private Filesystem $filesystem;

    private string $projectDir;

    private MockObject&PluginLifecycleService $pluginLifecycleService;

    private MockObject&CacheClearer $cacheClearer;

    private PluginCollection $plugins;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = Path::join(sys_get_temp_dir(), Uuid::randomHex());
        $this->pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);
        $this->plugins = new PluginCollection();

        /** @var StaticEntityRepository<PluginCollection> $pluginRepository */
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

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testInstallSortsPluginsByRequirements(): void
    {
        $dependentPlugin = $this->createPlugin('DependentPlugin', 'swag/dependent-plugin', ['swag/base-plugin']);
        $independentPlugin = $this->createPlugin('IndependentPlugin', 'swag/independent-plugin');
        $basePlugin = $this->createPlugin('BasePlugin', 'swag/base-plugin');
        $this->plugins->fill([$dependentPlugin, $independentPlugin, $basePlugin]);

        $installedPlugins = [];
        $installContext = $this->createMock(InstallContext::class);

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

    public function testInstallFailsWhenPluginComposerJsonIsMissingDuringRequirementSorting(): void
    {
        $missingPlugin = $this->createPluginEntity('MissingComposerPlugin', 'swag/missing-composer-plugin');
        $existingPlugin = $this->createPlugin('ExistingComposerPlugin', 'swag/existing-composer-plugin');
        $this->plugins->fill([$missingPlugin, $existingPlugin]);

        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');

        $this->expectException(PluginException::class);
        $this->expectExceptionMessage(\sprintf('Plugin "MissingComposerPlugin" has no composer.json at "%s".', Path::join($this->projectDir, 'custom/plugins/MissingComposerPlugin/composer.json')));

        $this->commandTester->execute(['plugins' => ['MissingComposerPlugin', 'ExistingComposerPlugin']], ['interactive' => false]);
    }

    public function testInstallSkipsAlreadyInstalledPlugin(): void
    {
        $plugin = $this->createPlugin('InstalledPlugin', 'swag/installed-plugin');
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
        $plugin = $this->createPlugin('InstalledPlugin', 'swag/installed-plugin');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $plugin->setActive(false);
        $this->plugins->add($plugin);

        $this->pluginLifecycleService->expects($this->never())->method('installPlugin');
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturn($this->createMock(ActivateContext::class));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['InstalledPlugin'],
            '--activate' => true,
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "InstalledPlugin" is already installed. Activating.', $this->commandTester->getDisplay());
    }

    public function testInstallWithActivateInstallsAndActivatesWithoutRequirementValidation(): void
    {
        $plugin = $this->createPlugin('NewPlugin', 'swag/new-plugin');
        $this->plugins->add($plugin);

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): InstallContext {
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createMock(InstallContext::class);
            });
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class), false, false)
            ->willReturn($this->createMock(ActivateContext::class));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['NewPlugin'],
            '--activate' => true,
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "NewPlugin" has been installed and activated successfully.', $this->commandTester->getDisplay());
    }

    public function testInstallWithReinstallUninstallsBeforeInstalling(): void
    {
        $plugin = $this->createPlugin('ReinstallPlugin', 'swag/reinstall-plugin');
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

                return $this->createMock(UninstallContext::class);
            });
        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use (&$calls): InstallContext {
                $calls[] = 'install';
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createMock(InstallContext::class);
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['ReinstallPlugin'],
            '--reinstall' => true,
        ], ['interactive' => false]));
        static::assertSame(['uninstall', 'install'], $calls);
    }

    public function testInstallPassesSkipAssetBuildStateToLifecycleService(): void
    {
        $plugin = $this->createPlugin('SkipAssetBuildPlugin', 'swag/skip-asset-build-plugin');
        $this->plugins->add($plugin);

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('installPlugin')
            ->with($plugin, static::callback(static fn (Context $context): bool => $context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING)))
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): InstallContext {
                $plugin->setInstalledAt(new \DateTimeImmutable());

                return $this->createMock(InstallContext::class);
            });

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['SkipAssetBuildPlugin'],
            '--skip-asset-build' => true,
        ], ['interactive' => false]));
    }

    /**
     * @param list<string> $requirements
     */
    private function createPlugin(string $name, string $composerName, array $requirements = []): PluginEntity
    {
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

        $this->filesystem->dumpFile(
            Path::join($this->projectDir, $pluginPath, 'composer.json'),
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
