<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Update\AppUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUpdater::class)]
class AppUpdaterTest extends TestCase
{
    public function testUpdatesOutdatedAppsAndReadsEveryInstalledAppWithOneSearch(): void
    {
        $outdatedId = Uuid::randomHex();
        $upToDateId = Uuid::randomHex();

        $extensions = new ExtensionCollection([
            $this->createExtension('OutdatedApp', $outdatedId, '2.0.0'),
            $this->createExtension('UpToDateApp', $upToDateId, '1.0.0'),
            // an extension that was never installed locally has no id and is skipped
            $this->createExtension('NotInstalledApp', null, '3.0.0'),
        ]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([
                $this->createApp($outdatedId, '1.0.0'),
                $this->createApp($upToDateId, '1.0.0'),
            ]),
        ]);

        $downloader = $this->createMock(ExtensionDownloader::class);
        $downloader->expects($this->once())->method('download')->with('OutdatedApp');

        $appLifecycle = $this->createMock(AbstractStoreAppLifecycleService::class);
        $appLifecycle->expects($this->once())->method('updateExtension')->with('OutdatedApp', false);

        $this->createUpdater($extensions, $appRepo, $downloader, $appLifecycle)
            ->updateApps(Context::createDefaultContext());

        // the single prepared search was consumed, so all apps were read at once
        static::assertSame([], $appRepo->searches);
    }

    public function testWithoutLocallyInstalledExtensionsNothingIsRead(): void
    {
        $extensions = new ExtensionCollection([$this->createExtension('NotInstalledApp', null, '3.0.0')]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $downloader = $this->createMock(ExtensionDownloader::class);
        $downloader->expects($this->never())->method('download');

        $appLifecycle = $this->createMock(AbstractStoreAppLifecycleService::class);
        $appLifecycle->expects($this->never())->method('updateExtension');

        $this->createUpdater($extensions, $appRepo, $downloader, $appLifecycle)
            ->updateApps(Context::createDefaultContext());
    }

    /**
     * @param StaticEntityRepository<AppCollection> $appRepo
     */
    private function createUpdater(
        ExtensionCollection $extensions,
        StaticEntityRepository $appRepo,
        ExtensionDownloader $downloader,
        AbstractStoreAppLifecycleService $appLifecycle
    ): AppUpdater {
        $dataProvider = $this->createMock(AbstractExtensionDataProvider::class);
        $dataProvider->method('getInstalledExtensions')->willReturn($extensions);

        return new AppUpdater($dataProvider, $appRepo, $downloader, $appLifecycle);
    }

    private function createExtension(string $name, ?string $localId, string $latestVersion): ExtensionStruct
    {
        return (new ExtensionStruct())->assign([
            'name' => $name,
            'label' => $name,
            'type' => ExtensionStruct::EXTENSION_TYPE_APP,
            'localId' => $localId,
            'latestVersion' => $latestVersion,
        ]);
    }

    private function createApp(string $id, string $version): AppEntity
    {
        $app = new AppEntity();
        $app->setUniqueIdentifier($id);
        $app->setId($id);
        $app->setVersion($version);

        return $app;
    }
}
