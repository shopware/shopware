<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand;
use Shopware\Core\Framework\Plugin\Command\Lifecycle\PluginDeactivateCommand;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractPluginLifecycleCommand::class)]
class AbstractPluginLifecycleCommandTest extends TestCase
{
    private MockObject&CacheClearer $cacheClearer;

    private PluginCollection $plugins;

    private PluginDeactivateCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->cacheClearer = $this->createMock(CacheClearer::class);
        $this->plugins = new PluginCollection();

        $pluginRepository = new StaticEntityRepository([
            fn (): PluginCollection => $this->plugins,
        ]);

        $this->command = new PluginDeactivateCommand(
            static::createStub(PluginLifecycleService::class),
            $pluginRepository,
            $this->cacheClearer
        );
        $this->command->setHelperSet(new HelperSet());

        $this->commandTester = new CommandTester($this->command);
    }

    #[TestDox('The clearCache option clears the cache after the lifecycle method')]
    public function testClearCacheOptionClearsCache(): void
    {
        $this->plugins->add($this->createActivePlugin());
        $this->cacheClearer->expects($this->once())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
            '--clearCache' => true,
        ], ['interactive' => false]));
        static::assertStringContainsString('Cache cleared', $this->commandTester->getDisplay());
    }

    #[TestDox('A failing cache clear is reported as error including the failure reason, the command still succeeds')]
    public function testClearCacheOptionReportsClearErrors(): void
    {
        $this->plugins->add($this->createActivePlugin());
        $this->cacheClearer
            ->expects($this->once())
            ->method('clear')
            ->willThrowException(new \RuntimeException('cache directory is not writable'));

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
            '--clearCache' => true,
        ], ['interactive' => false]));
        static::assertStringContainsString(
            'Error clearing cache: cache directory is not writable',
            $this->commandTester->getDisplay()
        );
    }

    #[TestDox('Without the clearCache option the command suggests running cache:clear')]
    public function testWithoutClearCacheOptionSuggestsCacheClearCommand(): void
    {
        $this->plugins->add($this->createActivePlugin());
        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
        ], ['interactive' => false]));
        static::assertStringContainsString(
            'You may want to clear the cache after deactivating plugin(s)',
            $this->commandTester->getDisplay()
        );
    }

    #[TestDox('The refresh option runs the plugin:refresh command silently on the console application')]
    public function testRefreshOptionRunsPluginRefreshCommand(): void
    {
        $this->plugins->add($this->createActivePlugin());
        $this->cacheClearer->expects($this->never())->method('clear');

        $application = $this->createMock(Application::class);
        $application->method('getHelperSet')->willReturn(new HelperSet());
        $application
            ->expects($this->once())
            ->method('doRun')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output): int {
                $this->assertSame('plugin:refresh', $input->getFirstArgument());
                $this->assertTrue($input->hasParameterOption('-s'));

                return Command::SUCCESS;
            });
        $this->command->setApplication($application);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
            '--refresh' => true,
        ], ['interactive' => false]));
    }

    #[TestDox('The refresh option throws when the command runs without a console application')]
    public function testRefreshOptionThrowsWhenConsoleApplicationIsMissing(): void
    {
        $this->plugins->add($this->createActivePlugin());
        $this->cacheClearer->expects($this->never())->method('clear');

        $this->expectExceptionObject(PluginException::consoleApplicationNotFound());

        $this->commandTester->execute([
            'plugins' => ['TestPlugin'],
            '--refresh' => true,
        ], ['interactive' => false]);
    }

    #[TestDox('handleClearCacheOption throws, because the method deprecation is enforced when v6.8.0.0 is active')]
    public function testHandleClearCacheOptionThrowsWhenV68IsActive(): void
    {
        $this->cacheClearer->expects($this->never())->method('clear');

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(
                AbstractPluginLifecycleCommand::class,
                AbstractPluginLifecycleCommand::class . '::handleClearCacheOption',
                'v6.8.0.0',
                'AbstractPluginLifecycleCommand::handleClearCache'
            )
        ));

        $this->runCommandUsingDeprecatedClearCacheOption(clearCache: true);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('handleClearCacheOption still clears the cache when v6.8.0.0 is inactive')]
    public function testHandleClearCacheOptionClearsCacheWhenV68IsInactive(): void
    {
        $this->cacheClearer->expects($this->once())->method('clear');

        $commandTester = $this->runCommandUsingDeprecatedClearCacheOption(clearCache: true);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Cache cleared', $commandTester->getDisplay());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('handleClearCacheOption still suggests running cache:clear when v6.8.0.0 is inactive')]
    public function testHandleClearCacheOptionSuggestsCacheClearCommandWhenV68IsInactive(): void
    {
        $this->cacheClearer->expects($this->never())->method('clear');

        $commandTester = $this->runCommandUsingDeprecatedClearCacheOption(clearCache: false);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString(
            'You may want to clear the cache after deactivating plugin(s)',
            $commandTester->getDisplay()
        );
    }

    /**
     * Runs a lifecycle command that still calls the deprecated method, which the shipped commands no longer do.
     */
    private function runCommandUsingDeprecatedClearCacheOption(bool $clearCache): CommandTester
    {
        $command = new DeprecatedClearCacheOptionCommand(
            static::createStub(PluginLifecycleService::class),
            StaticEntityRepository::of(PluginCollection::class),
            $this->cacheClearer
        );

        $command->setHelperSet(new HelperSet());

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            $clearCache ? ['plugins' => ['TestPlugin'], '--clearCache' => true] : ['plugins' => ['TestPlugin']],
            ['interactive' => false]
        );

        return $commandTester;
    }

    private function createActivePlugin(): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName('TestPlugin');
        $plugin->setLabel('TestPlugin');
        $plugin->setVersion('1.0.0');
        $plugin->setInstalledAt(new \DateTimeImmutable());
        $plugin->setActive(true);

        return $plugin;
    }
}

/**
 * Calls the deprecated handleClearCacheOption(), which the shipped lifecycle commands no longer do.
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedClearCacheOptionCommand extends AbstractPluginLifecycleCommand
{
    protected function configure(): void
    {
        $this->configureCommand('deactivate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->handleClearCacheOption($input, new ShopwareStyle($input, $output), 'deactivating');

        return self::SUCCESS;
    }
}
