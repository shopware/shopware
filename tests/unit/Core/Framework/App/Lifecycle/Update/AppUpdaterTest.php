<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppsUpdatedEvent;
use Shopware\Core\Framework\App\Lifecycle\Update\AppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(AppUpdater::class)]
class AppUpdaterTest extends TestCase
{
    private IdsCollection $ids;

    private Context $context;

    private CollectingEventDispatcher $eventDispatcher;

    private AbstractExtensionDataProvider&MockObject $extensionDataProvider;

    private ExtensionDownloader&MockObject $downloader;

    private AbstractStoreAppLifecycleService&MockObject $appLifecycle;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->extensionDataProvider = $this->createMock(AbstractExtensionDataProvider::class);
        $this->downloader = $this->createMock(ExtensionDownloader::class);
        $this->appLifecycle = $this->createMock(AbstractStoreAppLifecycleService::class);
    }

    public function testEventIsDispatchedWhenAppsAreUpdated(): void
    {
        $extension1 = $this->createExtension('app1', $this->ids->get('app1'), '1.0.0', '2.0.0');
        $extension2 = $this->createExtension('app2', $this->ids->get('app2'), '1.5.0', '2.5.0');

        $this->extensionDataProvider->expects($this->once())
            ->method('getInstalledExtensions')
            ->with($this->context, true)
            ->willReturn(new ExtensionCollection([$extension1, $extension2]));

        $app1 = new AppEntity();
        $app1->setId($this->ids->get('app1'));
        $app1->setName('app1');
        $app1->setVersion('1.0.0');

        $app2 = new AppEntity();
        $app2->setId($this->ids->get('app2'));
        $app2->setName('app2');
        $app2->setVersion('1.5.0');

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app1]),
            new AppCollection([$app2]),
        ]);

        $this->downloader->expects($this->exactly(2))
            ->method('download')
            ->willReturnCallback(function (string $name) use ($extension1, $extension2): PluginDownloadDataStruct {
                static::assertContains($name, [$extension1->getName(), $extension2->getName()]);

                return new PluginDownloadDataStruct();
            });

        $this->appLifecycle->expects($this->exactly(2))
            ->method('updateExtension')
            ->willReturnCallback(function (string $name, bool $requireConsent) use ($extension1, $extension2): void {
                static::assertContains($name, [$extension1->getName(), $extension2->getName()]);
                static::assertFalse($requireConsent);
            });

        $appUpdater = new AppUpdater(
            $this->extensionDataProvider,
            $appRepo,
            $this->downloader,
            $this->appLifecycle,
            $this->eventDispatcher
        );

        $appUpdater->updateApps($this->context);

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(AppsUpdatedEvent::class, $events[0]);

        static::assertSame(array_values($this->ids->getList(['app1', 'app2'])), $events[0]->appIds);
    }

    public function testEventIsNotDispatchedWhenNoAppsAreUpdated(): void
    {
        $extension = $this->createExtension('app1', $this->ids->get('app1'), '1.0.0', '1.0.0');

        $this->extensionDataProvider->expects($this->once())
            ->method('getInstalledExtensions')
            ->with($this->context, true)
            ->willReturn(new ExtensionCollection([$extension]));

        $app = new AppEntity();
        $app->setId($this->ids->get('app1'));
        $app->setName('app1');
        $app->setVersion('1.0.0');

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app]),
        ]);

        $this->downloader->expects($this->never())->method('download');
        $this->appLifecycle->expects($this->never())->method('updateExtension');

        $appUpdater = new AppUpdater(
            $this->extensionDataProvider,
            $appRepo,
            $this->downloader,
            $this->appLifecycle,
            $this->eventDispatcher
        );

        $appUpdater->updateApps($this->context);

        static::assertCount(0, $this->eventDispatcher->getEvents());
    }

    private function createExtension(string $name, string $id, string $currentVersion, string $latestVersion): ExtensionStruct
    {
        $extension = new ExtensionStruct();
        $extension->setName($name);
        $extension->setLocalId($id);
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);
        $extension->setVersion($currentVersion);
        $extension->setLatestVersion($latestVersion);

        return $extension;
    }
}
