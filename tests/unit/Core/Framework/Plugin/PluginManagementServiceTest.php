<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(PluginManagementService::class)]
class PluginManagementServiceTest extends TestCase
{
    public function testRefreshesPluginsAfterDownloadingFromStore(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())->method('refreshPlugins');

        $extractor = $this->createMock(PluginExtractor::class);
        $extractor->expects(static::once())
            ->method('extract');

        $pluginManagementService = new PluginManagementService(
            '',
            $this->createMock(PluginZipDetector::class),
            $extractor,
            $pluginService,
            $this->createMock(Filesystem::class),
            $this->createMock(CacheClearer::class),
            $client
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct('location', 'plugin'),
            Context::createDefaultContext()
        );
    }

    public function testExtractPluginWithDetectedPlugin(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);

        $pluginZipDetector = $this->createMock(PluginZipDetector::class);
        $pluginZipDetector->expects(static::once())
            ->method('detect')
            ->with('/some/zip/file.zip')
            ->willReturn(PluginManagementService::PLUGIN);

        $extractor = $this->createMock(PluginExtractor::class);
        $extractor->expects(static::once())
            ->method('extract')
            ->with('/some/zip/file.zip');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects(static::once())
            ->method('clearContainerCache');

        $pluginManagementService = new PluginManagementService(
            '',
            $pluginZipDetector,
            $extractor,
            $pluginService,
            $this->createMock(Filesystem::class),
            $cacheClearer,
            $client
        );

        $pluginManagementService->extractPluginZip(
            '/some/zip/file.zip',
        );
    }

    public function testExtractPluginWithDetectedApp(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);

        $pluginZipDetector = $this->createMock(PluginZipDetector::class);
        $pluginZipDetector->expects(static::once())
            ->method('detect')
            ->with('/some/zip/file.zip')
            ->willReturn(PluginManagementService::APP);

        $extractor = $this->createMock(PluginExtractor::class);
        $extractor->expects(static::once())
            ->method('extract')
            ->with('/some/zip/file.zip');

        $pluginManagementService = new PluginManagementService(
            '',
            $pluginZipDetector,
            $extractor,
            $pluginService,
            $this->createMock(Filesystem::class),
            $this->createMock(CacheClearer::class),
            $client
        );

        $pluginManagementService->extractPluginZip(
            '/some/zip/file.zip',
        );
    }

    public function testDoesNotRefreshPluginsAfterStoreDownloadIfTypeIsNotPlugin(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::never())
            ->method('refreshPlugins');

        $pluginManagementService = new PluginManagementService(
            '',
            $this->createMock(PluginZipDetector::class),
            $this->createMock(PluginExtractor::class),
            $pluginService,
            $this->createMock(Filesystem::class),
            $this->createMock(CacheClearer::class),
            $client
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct('location', 'app'),
            Context::createDefaultContext()
        );
    }

    public function testDeleteWhenManaged(): void
    {
        $pluginManagementService = new PluginManagementService(
            '',
            $this->createMock(PluginZipDetector::class),
            $this->createMock(PluginExtractor::class),
            $this->createMock(PluginService::class),
            $this->createMock(Filesystem::class),
            $this->createMock(CacheClearer::class),
            new Client(['handler' => new MockHandler()])
        );

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setName('Test');

        static::expectException(PluginException::class);
        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }

    /**
     * @param Response[] $responses
     */
    private function createClient(array $responses = []): Client
    {
        $mockHandler = new MockHandler($responses);

        return new Client(['handler' => $mockHandler]);
    }

    private function createPluginDownloadDataStruct(string $location, string $type): PluginDownloadDataStruct
    {
        $pluginDownloadData = new PluginDownloadDataStruct();
        $pluginDownloadData->assign([
            'location' => $location,
            'type' => $type,
        ]);

        return $pluginDownloadData;
    }
}
