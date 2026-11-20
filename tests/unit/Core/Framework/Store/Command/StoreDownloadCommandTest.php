<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Command\StoreDownloadCommand;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
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
    #[TestDox('The download runs with the admin flag of the given user')]
    public function testDownloadUsesAdminFlagOfUser(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setAdmin(true);

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('getDownloadDataForPlugin')
            ->willReturn(new PluginDownloadDataStruct());

        $pluginManagementService = $this->createMock(PluginManagementService::class);
        $pluginManagementService
            ->expects($this->once())
            ->method('downloadStorePlugin')
            ->willReturnCallback(function (PluginDownloadDataStruct $location, Context $context) use ($user): void {
                $source = $context->getSource();
                $this->assertInstanceOf(AdminApiSource::class, $source);
                $this->assertSame($user->getId(), $source->getUserId());
                $this->assertTrue($source->isAdmin());
            });

        $commandTester = new CommandTester(new StoreDownloadCommand(
            $storeClient,
            StaticEntityRepository::of(PluginCollection::class, [new PluginCollection(), new PluginCollection()]),
            $pluginManagementService,
            static::createStub(PluginLifecycleService::class),
            StaticEntityRepository::of(UserCollection::class, [new UserCollection([$user])]),
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--pluginName' => 'SwagExample',
            '--user' => 'admin',
        ]));
    }
}
