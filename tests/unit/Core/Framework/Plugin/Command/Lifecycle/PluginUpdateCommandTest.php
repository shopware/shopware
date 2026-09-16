<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateCommand;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginUpdateCommand::class)]
class PluginUpdateCommandTest extends TestCase
{
    private MockObject&PluginLifecycleService $pluginLifecycleService;

    private PluginCollection $plugins;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $this->plugins = new PluginCollection();

        $pluginRepository = new StaticEntityRepository([
            fn (): PluginCollection => $this->plugins,
        ]);

        $command = new PluginUpdateCommand(
            $this->pluginLifecycleService,
            $pluginRepository,
            static::createStub(CacheClearer::class)
        );
        $command->setHelperSet(new HelperSet());

        $this->commandTester = new CommandTester($command);
    }

    #[TestDox('An installed plugin is updated')]
    public function testUpdatesInstalledPlugin(): void
    {
        $plugin = $this->createPluginEntity(installed: true);
        $this->plugins->add($plugin);

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('updatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturn(static::createStub(UpdateContext::class));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
        ], ['interactive' => false]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Plugin "TestPlugin" has been updated successfully.', $display);
        static::assertStringContainsString('Updated 1 plugin(s).', $display);
    }

    #[TestDox('A plugin that is not installed is skipped')]
    public function testSkipsPluginThatIsNotInstalled(): void
    {
        $this->plugins->add($this->createPluginEntity(installed: false));

        $this->pluginLifecycleService->expects($this->never())->method('updatePlugin');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "TestPlugin" is not installed. Skipping.', $this->commandTester->getDisplay());
    }

    private function createPluginEntity(bool $installed): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName('TestPlugin');
        $plugin->setLabel('TestPlugin');
        $plugin->setVersion('1.0.0');
        $plugin->setActive(false);
        if ($installed) {
            $plugin->setInstalledAt(new \DateTimeImmutable());
        }

        return $plugin;
    }
}
