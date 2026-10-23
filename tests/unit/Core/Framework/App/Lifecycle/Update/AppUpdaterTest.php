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
    public function testTheAppsOfEveryExtensionAreLoadedWithOneSearch(): void
    {
        $context = Context::createDefaultContext();

        $firstApp = self::app('1.0.0');
        $secondApp = self::app('1.0.0');

        $extensions = new ExtensionCollection([
            self::extension('first', $firstApp->getId(), '1.0.0'),
            self::extension('second', $secondApp->getId(), '1.0.0'),
        ]);

        $extensionDataProvider = static::createStub(AbstractExtensionDataProvider::class);
        $extensionDataProvider->method('getInstalledExtensions')->willReturn($extensions);

        // a single queued result, so a second search would fail the test
        $appRepository = new StaticEntityRepository([new AppCollection([$firstApp, $secondApp])]);

        $downloader = $this->createMock(ExtensionDownloader::class);
        $downloader->expects($this->never())->method('download');

        $updater = new AppUpdater(
            $extensionDataProvider,
            $appRepository,
            $downloader,
            static::createStub(AbstractStoreAppLifecycleService::class)
        );

        $updater->updateApps($context);

        static::assertSame([], $appRepository->searches);
    }

    private static function app(string $version): AppEntity
    {
        $app = new AppEntity();
        $id = Uuid::randomHex();
        $app->setId($id);
        $app->setUniqueIdentifier($id);
        $app->setVersion($version);

        return $app;
    }

    private static function extension(string $name, string $localId, string $latestVersion): ExtensionStruct
    {
        return ExtensionStruct::fromArray([
            'name' => $name,
            'label' => $name,
            'type' => ExtensionStruct::EXTENSION_TYPE_APP,
            'localId' => $localId,
            'latestVersion' => $latestVersion,
            'version' => $latestVersion,
            'active' => true,
        ]);
    }
}
