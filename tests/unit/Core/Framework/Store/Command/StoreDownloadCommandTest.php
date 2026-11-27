<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
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
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreDownloadCommand::class)]
class StoreDownloadCommandTest extends TestCase
{
    #[TestDox('Only the store request runs with the given user, the plugin download and update run in the CLI context')]
    public function testOnlyStoreRequestUsesUserContext(): void
    {
        $userId = Uuid::randomHex();

        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setUpgradeVersion('2.0.0');

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('getDownloadDataForPlugin')
            ->willReturnCallback(function (string $pluginName, Context $context) use ($userId): PluginDownloadDataStruct {
                $source = $context->getSource();
                $this->assertInstanceOf(AdminApiSource::class, $source);
                $this->assertSame($userId, $source->getUserId());

                return new PluginDownloadDataStruct();
            });

        $pluginManagementService = $this->createMock(PluginManagementService::class);
        $pluginManagementService
            ->expects($this->once())
            ->method('downloadStorePlugin')
            ->willReturnCallback(function (PluginDownloadDataStruct $location, Context $context): void {
                $this->assertInstanceOf(SystemSource::class, $context->getSource());
            });

        $pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycleService
            ->expects($this->once())
            ->method('updatePlugin')
            ->willReturnCallback(function (PluginEntity $plugin, Context $context): UpdateContext {
                $this->assertInstanceOf(SystemSource::class, $context->getSource());

                return static::createStub(UpdateContext::class);
            });

        $commandTester = new CommandTester(new StoreDownloadCommand(
            $storeClient,
            StaticEntityRepository::of(PluginCollection::class, [new PluginCollection(), new PluginCollection([$plugin])]),
            $pluginManagementService,
            $pluginLifecycleService,
            StaticEntityRepository::of(UserCollection::class, [[$userId]]),
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--pluginName' => 'SwagExample',
            '--user' => 'editor',
        ]));
    }
}
