<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreAppLifecycleService::class)]
class StoreAppLifecycleServiceTest extends TestCase
{
    public function testActivatesInstalledApp(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('TestApp', $context)
            ->willReturn($app);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('activate')
            ->with('app-id', $context);

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection()]);

        $storeAppLifecycleService = new StoreAppLifecycleService(
            static::createStub(StoreClient::class),
            static::createStub(AppLoader::class),
            $appLifecycle,
            $appStorage,
            $salesChannelRepository,
            null,
            static::createStub(AppConfirmationDeltaProvider::class),
        );

        $storeAppLifecycleService->activateExtension('TestApp', $context);
    }

    public function testDeactivatesInstalledApp(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('TestApp', $context)
            ->willReturn($app);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('deactivate')
            ->with('app-id', $context);

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection()]);

        $storeAppLifecycleService = new StoreAppLifecycleService(
            static::createStub(StoreClient::class),
            static::createStub(AppLoader::class),
            $appLifecycle,
            $appStorage,
            $salesChannelRepository,
            null,
            static::createStub(AppConfirmationDeltaProvider::class),
        );

        $storeAppLifecycleService->deactivateExtension('TestApp', $context);
    }

    public function testCancelsSubscriptionAndDeletesApp(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');
        $context = Context::createDefaultContext();

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('cancelSubscription')
            ->with(123, $context);

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findById')
            ->with('app-id', $context)
            ->willReturn($app);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('TestApp', $context)
            ->willReturn(null);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('uninstall')
            ->with('TestApp', ['id' => 'app-id'], $context);

        $appLoader = $this->createMock(AppLoader::class);
        $appLoader
            ->expects($this->once())
            ->method('deleteApp')
            ->with('TestApp');

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection()]);

        $storeAppLifecycleService = new StoreAppLifecycleService(
            $storeClient,
            $appLoader,
            $appLifecycle,
            $appStorage,
            $salesChannelRepository,
            null,
            static::createStub(AppConfirmationDeltaProvider::class),
        );

        $storeAppLifecycleService->removeExtensionAndCancelSubscription(123, 'TestApp', 'app-id', false, $context);
    }

    public function testCannotCancelSubscriptionForMissingApp(): void
    {
        $context = Context::createDefaultContext();

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->never())->method('cancelSubscription');

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findById')
            ->with('missing-app-id', $context)
            ->willReturn(null);

        $this->expectExceptionObject(StoreException::extensionNotFoundFromId('missing-app-id'));

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection()]);

        $storeAppLifecycleService = new StoreAppLifecycleService(
            $storeClient,
            static::createStub(AppLoader::class),
            static::createStub(AbstractAppLifecycle::class),
            $appStorage,
            $salesChannelRepository,
            null,
            static::createStub(AppConfirmationDeltaProvider::class),
        );

        $storeAppLifecycleService->removeExtensionAndCancelSubscription(123, 'TestApp', 'missing-app-id', false, $context);
    }
}
