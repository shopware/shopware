<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginActivateCommand;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
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
#[CoversClass(PluginActivateCommand::class)]
class PluginActivateCommandTest extends TestCase
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

        $command = new PluginActivateCommand(
            $this->pluginLifecycleService,
            $pluginRepository,
            static::createStub(CacheClearer::class)
        );
        $command->setHelperSet(new HelperSet());

        $this->commandTester = new CommandTester($command);
    }

    #[TestDox('An installed, inactive plugin is activated')]
    public function testActivatesInstalledInactivePlugin(): void
    {
        $plugin = $this->createPluginEntity(installed: true, active: false);
        $this->plugins->add($plugin);

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('activatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturn(static::createStub(ActivateContext::class));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
        ], ['interactive' => false]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Plugin "TestPlugin" has been activated successfully.', $display);
        static::assertStringContainsString('Activated 1 plugin(s).', $display);
    }

    #[TestDox('A plugin that is not installed is skipped')]
    public function testSkipsPluginThatIsNotInstalled(): void
    {
        $this->plugins->add($this->createPluginEntity(installed: false, active: false));

        $this->pluginLifecycleService->expects($this->never())->method('activatePlugin');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "TestPlugin" must be installed. Skipping.', $this->commandTester->getDisplay());
    }

    #[TestDox('A plugin that is already active is skipped')]
    public function testSkipsPluginThatIsAlreadyActive(): void
    {
        $this->plugins->add($this->createPluginEntity(installed: true, active: true));

        $this->pluginLifecycleService->expects($this->never())->method('activatePlugin');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
        ], ['interactive' => false]));
        static::assertStringContainsString('Plugin "TestPlugin" is already active. Skipping.', $this->commandTester->getDisplay());
    }

    private function createPluginEntity(bool $installed, bool $active): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName('TestPlugin');
        $plugin->setLabel('TestPlugin');
        $plugin->setVersion('1.0.0');
        $plugin->setActive($active);
        if ($installed) {
            $plugin->setInstalledAt(new \DateTimeImmutable());
        }

        return $plugin;
    }
}
