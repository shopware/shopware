<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginComposerDependencyService;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginComposerDependencyService::class)]
class PluginComposerDependencyServiceTest extends TestCase
{
    private const BASE_CLASS = 'Swag\Test\SwagTest';

    private MockObject&CommandExecutor $executor;

    private MockObject&PluginService $pluginService;

    private ArrayAdapter $restartSignalCachePool;

    private bool $executesComposerCommands = true;

    private string $projectDir;

    /**
     * @var array{root: array<string, mixed>, versions: array<string, array<string, mixed>>}
     */
    private array $installedVersionsBefore;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(CommandExecutor::class);
        $this->pluginService = $this->createMock(PluginService::class);
        $this->restartSignalCachePool = new ArrayAdapter();
        $this->projectDir = sys_get_temp_dir() . '/plugin-composer-dependency-' . bin2hex(random_bytes(6));
        $this->installedVersionsBefore = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        InstalledVersions::reload($this->installedVersionsBefore);
        (new Filesystem())->remove($this->projectDir);
    }

    public function testRequireDependencyRequiresTheVersionOnDisk(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0', upgradeVersion: '1.1.0');

        $this->executor->expects($this->once())->method('require')->with('swag/test:1.1.0', 'SwagTest');
        $this->pluginService->expects($this->once())->method('refreshPlugins');

        static::assertTrue($this->createService()->requireDependency($plugin, Context::createDefaultContext()));
        static::assertTrue($this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY)->isHit());
    }

    public function testRequireDependencyMakesTheNewPackageVisibleToThisProcess(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0');
        $this->executor->expects($this->once())->method('require')->willReturnCallback(function (): void {
            (new Filesystem())->dumpFile(
                $this->projectDir . '/vendor/composer/installed.php',
                '<?php return ' . var_export($this->installedVersions('1.0.0.0'), true) . ';'
            );
        });

        $service = $this->createService();
        $service->requireDependency($plugin, Context::createDefaultContext());

        static::assertFalse($service->isRequireNeeded($plugin, '1.0.0'));
    }

    public function testRequireDependencySkipsAPackageInstalledInThatVersion(): void
    {
        InstalledVersions::reload($this->installedVersions('1.0.0.0'));

        $this->executor->expects($this->never())->method('require');

        static::assertFalse($this->createService()->requireDependency($this->createPlugin(version: '1.0.0'), Context::createDefaultContext()));
    }

    public function testRequireDependencySkipsPluginsThatDoNotAskForComposer(): void
    {
        $this->executesComposerCommands = false;

        $this->executor->expects($this->never())->method('require');

        static::assertFalse($this->createService()->requireDependency($this->createPlugin(version: '1.0.0'), Context::createDefaultContext()));
    }

    public function testRequireDependencySkipsClusterSetups(): void
    {
        $this->executor->expects($this->never())->method('require');

        static::assertFalse($this->createService(isClusterSetup: true)->requireDependency($this->createPlugin(version: '1.0.0'), Context::createDefaultContext()));
    }

    public function testRequireDependencyNeedsAComposerName(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0', composerName: null);

        $this->expectExceptionObject(PluginException::composerJsonInvalid('custom/plugins/SwagTest/composer.json', ['No name defined in composer.json']));

        $this->createService()->requireDependency($plugin, Context::createDefaultContext());
    }

    public function testRequireDependencyNeedsTheLoadedPluginClass(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0');
        $plugin->setBaseClass('Swag\Missing\SwagMissing');

        $this->expectExceptionObject(PluginException::baseClassNotFound('Swag\Missing\SwagMissing'));

        $this->createService()->requireDependency($plugin, Context::createDefaultContext());
    }

    public function testRemoveDependencyRemovesThePackageOfAnUninstalledPlugin(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0', managedByComposer: true);

        $this->executor->expects($this->once())->method('remove')->with('swag/test', 'SwagTest');
        $this->pluginService->expects($this->once())->method('refreshPlugins');

        static::assertTrue($this->createService()->removeDependency($plugin, Context::createDefaultContext()));
    }

    public function testRemoveDependencyRemovesThePackageOfAPluginThatNoLongerAsksForComposer(): void
    {
        $this->executesComposerCommands = false;
        $plugin = $this->createPlugin(version: '1.0.0', managedByComposer: true);
        $plugin->setInstalledAt(new \DateTimeImmutable());

        $this->executor->expects($this->once())->method('remove')->with('swag/test', 'SwagTest');

        static::assertTrue($this->createService()->removeDependency($plugin, Context::createDefaultContext()));
    }

    public function testRemoveDependencyKeepsThePackageOfAnInstalledPluginThatAsksForComposer(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0', managedByComposer: true);
        $plugin->setInstalledAt(new \DateTimeImmutable());

        $this->executor->expects($this->never())->method('remove');

        static::assertFalse($this->createService()->removeDependency($plugin, Context::createDefaultContext()));
    }

    public function testRemoveDependencyKeepsPackagesTheProjectRequiresOutsideCustom(): void
    {
        $plugin = $this->createPlugin(version: '1.0.0', managedByComposer: true);
        $plugin->setPath('vendor/swag/test');

        $this->executor->expects($this->never())->method('remove');

        static::assertFalse($this->createService()->removeDependency($plugin, Context::createDefaultContext()));
    }

    public function testRemoveDependencySkipsPluginsComposerDoesNotManage(): void
    {
        $this->executor->expects($this->never())->method('remove');

        static::assertFalse($this->createService()->removeDependency($this->createPlugin(version: '1.0.0'), Context::createDefaultContext()));
    }

    public function testRemoveDependencySkipsClusterSetups(): void
    {
        $this->executor->expects($this->never())->method('remove');

        $plugin = $this->createPlugin(version: '1.0.0', managedByComposer: true);

        static::assertFalse($this->createService(isClusterSetup: true)->removeDependency($plugin, Context::createDefaultContext()));
    }

    private function createService(bool $isClusterSetup = false): PluginComposerDependencyService
    {
        $pluginClass = static::createStub(Plugin::class);
        $pluginClass->method('executeComposerCommands')->willReturnCallback(fn (): bool => $this->executesComposerCommands);

        return new PluginComposerDependencyService(
            new KernelPluginCollection([self::BASE_CLASS => $pluginClass]),
            $this->executor,
            $this->pluginService,
            new VersionSanitizer(),
            $this->restartSignalCachePool,
            new MockClock(),
            $isClusterSetup,
            $this->projectDir,
        );
    }

    private function createPlugin(string $version, ?string $upgradeVersion = null, bool $managedByComposer = false, ?string $composerName = 'swag/test'): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setName('SwagTest');
        $plugin->setBaseClass(self::BASE_CLASS);
        if ($composerName !== null) {
            $plugin->setComposerName($composerName);
        }
        $plugin->setPath('custom/plugins/SwagTest');
        $plugin->setVersion($version);
        $plugin->setUpgradeVersion($upgradeVersion);
        $plugin->setManagedByComposer($managedByComposer);

        return $plugin;
    }

    /**
     * @return array{root: array<string, mixed>, versions: array<string, array<string, mixed>>}
     */
    private function installedVersions(string $pluginVersion): array
    {
        return [
            'root' => [
                'name' => 'shopware/production',
                'pretty_version' => '6.7.9999999.9999999-dev',
                'version' => '6.7.9999999.9999999-dev',
                'reference' => null,
                'type' => 'project',
                'install_path' => $this->projectDir,
                'aliases' => [],
                'dev' => false,
            ],
            'versions' => [
                'swag/test' => [
                    'pretty_version' => $pluginVersion,
                    'version' => $pluginVersion,
                    'reference' => null,
                    'type' => 'shopware-platform-plugin',
                    'install_path' => $this->projectDir . '/custom/plugins/SwagTest',
                    'aliases' => [],
                    'dev_requirement' => false,
                ],
            ],
        ];
    }
}
