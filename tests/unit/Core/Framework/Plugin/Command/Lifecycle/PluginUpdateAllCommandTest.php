<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateAllCommand;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(PluginUpdateAllCommand::class)]
class PluginUpdateAllCommandTest extends TestCase
{
    public function testNoUpdates(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())->method('refreshPlugins');

        $pluginRepository = new StaticEntityRepository([new PluginCollection([
            $this->createPlugin('Test'),
            $this->createPlugin('Test2'),
        ])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects(static::never())->method('updatePlugin');

        $command = new PluginUpdateAllCommand($pluginService, $pluginRepository, $pluginLifecycleService);
        $command->setHelperSet(new HelperSet());

        $tester = new CommandTester($command);
        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testUpdatableButNotActive(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())->method('refreshPlugins');

        $pluginRepository = new StaticEntityRepository([new PluginCollection([
            $this->createPlugin('Test'),
            $this->createPlugin('Test2', false, '2.0.0'),
        ])]);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects(static::never())->method('updatePlugin');

        $command = new PluginUpdateAllCommand($pluginService, $pluginRepository, $pluginLifecycleService);
        $command->setHelperSet(new HelperSet());
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testUpdatesOnlyAvailablePlugins(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())->method('refreshPlugins');

        $updateAblePlugin = $this->createPlugin('Test2', upgradeVersion: '1.0.1');
        $pluginRepository = new StaticEntityRepository([new PluginCollection([
            $this->createPlugin('Test'),
            $updateAblePlugin,
        ])]);

        $updateMock = $this->createMock(UpdateContext::class);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects(static::once())
            ->method('updatePlugin')
            ->with($updateAblePlugin)
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use ($updateMock) {
                $plugin->setVersion((string) $plugin->getUpgradeVersion());
                $plugin->setUpgradeVersion(null);
                static::assertFalse($context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING));

                return $updateMock;
            });

        $command = new PluginUpdateAllCommand($pluginService, $pluginRepository, $pluginLifecycleService);
        $command->setHelperSet(new HelperSet());

        $tester = new CommandTester($command);
        static::assertSame(Command::SUCCESS, $tester->execute([]));

        static::assertSame('Updated plugin Test2 from version 1.0.0 to version 1.0.1', trim($tester->getDisplay()));
    }

    public function testUpdatesOnlyAvailablePluginsSkipAssetBuild(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())->method('refreshPlugins');

        $updateAblePlugin = $this->createPlugin('Test2', upgradeVersion: '1.0.1');
        $pluginRepository = new StaticEntityRepository([new PluginCollection([
            $this->createPlugin('Test'),
            $updateAblePlugin,
        ])]);

        $updateMock = $this->createMock(UpdateContext::class);

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects(static::once())
            ->method('updatePlugin')
            ->with($updateAblePlugin)
            ->willReturnCallback(function (PluginEntity $plugin, Context $context) use ($updateMock) {
                $plugin->setVersion((string) $plugin->getUpgradeVersion());
                $plugin->setUpgradeVersion(null);
                static::assertTrue($context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING));

                return $updateMock;
            });

        $command = new PluginUpdateAllCommand($pluginService, $pluginRepository, $pluginLifecycleService);
        $command->setHelperSet(new HelperSet());

        $tester = new CommandTester($command);
        static::assertSame(Command::SUCCESS, $tester->execute(['--skip-asset-build' => true]));

        static::assertSame('Updated plugin Test2 from version 1.0.0 to version 1.0.1', trim($tester->getDisplay()));
    }

    private function createPlugin(string $name, bool $active = true, ?string $upgradeVersion = null): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName($name);
        $plugin->setVersion('1.0.0');
        $plugin->setActive($active);
        $plugin->setUpgradeVersion($upgradeVersion);

        return $plugin;
    }
}
