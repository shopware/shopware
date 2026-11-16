<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\ConsoleCommandProcessRunner;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginComposerDependencyService;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Command\StoreDownloadCommand;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreDownloadCommand::class)]
class StoreDownloadCommandTest extends TestCase
{
    public function testRunsTheUpdateInAProcessOfItsOwnWhenAPackageWasRequired(): void
    {
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->willReturn(true);
        $composerDependencyService->expects($this->never())->method('removeDependency');
        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->never())->method('updatePlugin');
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner
            ->expects($this->once())
            ->method('runCommand')
            ->with('plugin:update', ['SwagFirst'], static::isInstanceOf(OutputInterface::class))
            ->willReturn(7);

        $commandTester = new CommandTester($this->createCommand($pluginLifecycleService, $composerDependencyService, $commandRunner));

        static::assertSame(7, $commandTester->execute(['--pluginName' => 'SwagFirst']));
    }

    public function testUpdatesInTheSameProcessWhenNoPackageWasRequired(): void
    {
        $composerDependencyService = $this->createMock(PluginComposerDependencyService::class);
        $composerDependencyService->expects($this->once())->method('requireDependency')->willReturn(false);
        $composerDependencyService->expects($this->once())->method('removeDependency')->willReturn(false);
        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService->expects($this->once())->method('updatePlugin')->willReturn(static::createStub(UpdateContext::class));
        $commandRunner = $this->createMock(ConsoleCommandProcessRunner::class);
        $commandRunner->expects($this->never())->method('runCommand');

        $commandTester = new CommandTester($this->createCommand($pluginLifecycleService, $composerDependencyService, $commandRunner));

        static::assertSame(Command::SUCCESS, $commandTester->execute(['--pluginName' => 'SwagFirst']));
    }

    private function createCommand(
        PluginLifecycleService $pluginLifecycleService,
        PluginComposerDependencyService $composerDependencyService,
        ConsoleCommandProcessRunner $commandRunner,
    ): StoreDownloadCommand {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName('SwagFirst');
        $plugin->setVersion('1.0.0');
        $plugin->setUpgradeVersion('2.0.0');
        $plugin->setManagedByComposer(false);

        $storeClient = static::createStub(StoreClient::class);
        $storeClient->method('getDownloadDataForPlugin')->willReturn(new PluginDownloadDataStruct());
        $pluginManagementService = $this->createMock(PluginManagementService::class);
        $pluginManagementService->expects($this->once())->method('downloadStorePlugin');

        $command = new StoreDownloadCommand(
            $storeClient,
            new StaticEntityRepository([new PluginCollection([$plugin]), new PluginCollection([$plugin])]),
            $pluginManagementService,
            $pluginLifecycleService,
            StaticEntityRepository::of(UserCollection::class, []),
            $composerDependencyService,
            $commandRunner,
        );
        $command->setHelperSet(new HelperSet());

        return $command;
    }
}
