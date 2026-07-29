<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\PluginRefreshCommand;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginService;
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
#[CoversClass(PluginRefreshCommand::class)]
class PluginRefreshCommandTest extends TestCase
{
    private MockObject&PluginService $pluginService;

    private PluginRefreshCommand $command;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->pluginService = $this->createMock(PluginService::class);

        $this->command = new PluginRefreshCommand($this->pluginService);
        $this->command->setHelperSet(new HelperSet());

        $this->commandTester = new CommandTester($this->command);
    }

    #[TestDox('Refreshing the plugin list runs the plugin:list command afterwards')]
    public function testRefreshRunsPluginListCommand(): void
    {
        $this->pluginService
            ->expects($this->once())
            ->method('refreshPlugins')
            ->willReturn(new ExceptionCollection());

        $application = $this->createMock(Application::class);
        $application->method('getHelperSet')->willReturn(new HelperSet());
        $application
            ->expects($this->once())
            ->method('doRun')
            ->willReturnCallback(function (InputInterface $input, OutputInterface $output): int {
                $this->assertSame('plugin:list', $input->getFirstArgument());

                return Command::SUCCESS;
            });
        $this->command->setApplication($application);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('Plugin list refreshed', $this->commandTester->getDisplay());
    }

    #[TestDox('Errors collected during the refresh are displayed, keyed errors are prefixed with the plugin name')]
    public function testRefreshDisplaysCollectedErrors(): void
    {
        $errors = new ExceptionCollection();
        $errors->add(PluginException::cannotDeleteManaged('SwagManagedPlugin'));
        $errors->set('SwagBrokenPlugin', PluginException::notInstalled('SwagBrokenPlugin'));

        $this->pluginService
            ->expects($this->once())
            ->method('refreshPlugins')
            ->willReturn($errors);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--skipPluginList' => true]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Errors occurred while refreshing plugin list', $display);
        static::assertStringContainsString('Plugin SwagManagedPlugin is managed by Composer and cannot be deleted', $display);
        static::assertStringContainsString('SwagBrokenPlugin: Plugin "SwagBrokenPlugin" is not installed.', $display);
    }

    #[TestDox('The skipPluginList option skips running the plugin:list command')]
    public function testSkipPluginListOptionSkipsListCommand(): void
    {
        $this->pluginService
            ->expects($this->once())
            ->method('refreshPlugins')
            ->willReturn(new ExceptionCollection());

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--skipPluginList' => true]));
        static::assertStringContainsString('Plugin list refreshed', $this->commandTester->getDisplay());
    }

    #[TestDox('Running the plugin:list command without a console application throws')]
    public function testThrowsWhenConsoleApplicationIsMissing(): void
    {
        $this->pluginService
            ->expects($this->once())
            ->method('refreshPlugins')
            ->willReturn(new ExceptionCollection());

        $this->expectExceptionObject(PluginException::consoleApplicationNotFound());

        $this->commandTester->execute([]);
    }
}
