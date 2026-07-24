<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\PluginZipImportCommand;
use Shopware\Core\Framework\Plugin\Exception\ExceptionCollection;
use Shopware\Core\Framework\Plugin\Exception\NoPluginFoundInZipException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginZipImportCommand::class)]
class PluginZipImportCommandTest extends TestCase
{
    private MockObject&PluginManagementService $pluginManagementService;

    private MockObject&PluginService $pluginService;

    private MockObject&CacheClearer $cacheClearer;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->pluginManagementService = $this->createMock(PluginManagementService::class);
        $this->pluginService = $this->createMock(PluginService::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);

        $command = new PluginZipImportCommand(
            $this->pluginManagementService,
            $this->pluginService,
            $this->cacheClearer
        );
        $command->setHelperSet(new HelperSet());

        $this->commandTester = new CommandTester($command);
    }

    #[TestDox('Importing a plugin zip clears the container cache and refreshes the plugin list')]
    public function testImportsPluginZipAndRefreshesPluginList(): void
    {
        $this->pluginManagementService
            ->expects($this->once())
            ->method('extractPluginZip')
            ->with('/tmp/SwagExample.zip', false)
            ->willReturn(PluginManagementService::PLUGIN);
        $this->cacheClearer->expects($this->once())->method('clearContainerCache');
        $this->pluginService
            ->expects($this->once())
            ->method('refreshPlugins')
            ->willReturn(new ExceptionCollection());

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'zip-file' => '/tmp/SwagExample.zip',
        ]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Successfully import zip file SwagExample.zip', $display);
        static::assertStringContainsString('Plugin list refreshed', $display);
    }

    #[TestDox('Importing an app zip skips the container cache clear, the no-refresh option skips the plugin list refresh')]
    public function testImportsAppZipWithoutRefresh(): void
    {
        $this->pluginManagementService
            ->expects($this->once())
            ->method('extractPluginZip')
            ->with('/tmp/SwagApp.zip', true)
            ->willReturn(PluginManagementService::APP);
        $this->cacheClearer->expects($this->never())->method('clearContainerCache');
        $this->pluginService->expects($this->never())->method('refreshPlugins');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            'zip-file' => '/tmp/SwagApp.zip',
            '--no-refresh' => true,
            '--delete' => true,
        ]));
        static::assertStringContainsString('Successfully import zip file SwagApp.zip', $this->commandTester->getDisplay());
    }

    #[TestDox('A zip file without a plugin is reported as error and the command fails')]
    public function testFailsWhenZipContainsNoPlugin(): void
    {
        $this->pluginManagementService
            ->expects($this->once())
            ->method('extractPluginZip')
            ->willThrowException(new NoPluginFoundInZipException('/tmp/empty.zip'));
        $this->cacheClearer->expects($this->never())->method('clearContainerCache');
        $this->pluginService->expects($this->never())->method('refreshPlugins');

        static::assertSame(Command::FAILURE, $this->commandTester->execute([
            'zip-file' => '/tmp/empty.zip',
        ]));
        static::assertStringContainsString('No plugin was found in the zip archive: /tmp/empty.zip', $this->commandTester->getDisplay());
    }
}
