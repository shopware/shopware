<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;

/**
 * @internal
 */
#[CoversClass(ExtensionCompatibility::class)]
class ExtensionCompatibilityTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testGetExtension(string $file, string $statusName, ?string $statusColor): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('getExtensionCompatibilities')->willReturn(json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR));

        $pluginCompatibility = new ExtensionCompatibility(
            $storeClient,
            $this->getExtensionDataProvider()
        );

        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $getExtensionCompatibilities = $pluginCompatibility->getExtensionCompatibilities($version, Context::createDefaultContext());

        static::assertSame($statusName, $getExtensionCompatibilities[0]['statusName']);
        static::assertSame($statusColor, $getExtensionCompatibilities[0]['statusColor']);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function statusProvider(): iterable
    {
        yield 'future' => [
            __DIR__ . './../_fixtures/responses/extension-yellow.json',
            'updatableFuture',
            'yellow',
        ];

        yield 'green' => [
            __DIR__ . './../_fixtures/responses/extension-green.json',
            'compatible',
            null,
        ];

        yield 'red' => [
            __DIR__ . './../_fixtures/responses/extension-red.json',
            'notCompatible',
            null,
        ];
    }

    public function testGetExtensionWhenInvalidVersion(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->method('getExtensionCompatibilities')
            ->willThrowException(new ClientException('test', new Request('GET', '/'), new Response(400)));

        $pluginCompatibility = new ExtensionCompatibility(
            $storeClient,
            $this->getExtensionDataProvider()
        );

        $version = new Version();
        $version->assign([
            'version' => '6.6.0.0',
        ]);

        $getExtensionCompatibilities = $pluginCompatibility->getExtensionCompatibilities($version, Context::createDefaultContext());

        static::assertSame('notInStore', $getExtensionCompatibilities[0]['statusName']);
        static::assertNull($getExtensionCompatibilities[0]['statusColor']);
    }

    public function testGetExtensionWhenOtherException(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->method('getExtensionCompatibilities')
            ->willThrowException(new ClientException('test', new Request('GET', '/'), new Response(500)));

        $pluginCompatibility = new ExtensionCompatibility(
            $storeClient,
            $this->getExtensionDataProvider()
        );

        static::expectException(ClientException::class);
        $pluginCompatibility->getExtensionCompatibilities(new Version(), Context::createDefaultContext());
    }

    public function testExtensionsToDeactivateNoFilter(): void
    {
        $pluginCompatibility = new ExtensionCompatibility(
            $this->getStoreClient(),
            $this->getExtensionDataProvider()
        );

        static::assertEmpty($pluginCompatibility->getExtensionsToDeactivate(new Version(), Context::createDefaultContext(), ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_NONE));
    }

    public function testExtensionsToDeactivateAll(): void
    {
        $pluginCompatibility = new ExtensionCompatibility(
            $this->getStoreClient(),
            $this->getExtensionDataProvider()
        );

        $extensionStructs = $pluginCompatibility->getExtensionsToDeactivate(new Version(), Context::createDefaultContext(), ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL);

        static::assertCount(1, $extensionStructs);
        static::assertSame('TestApp', $extensionStructs[0]->getName());
    }

    public function testExtensionsToDeactivateOnlyInCompatibleWithInCompatible(): void
    {
        $pluginCompatibility = new ExtensionCompatibility(
            $this->getStoreClient(__DIR__ . './../_fixtures/responses/extension-yellow.json'),
            $this->getExtensionDataProvider()
        );

        $extensionStructs = $pluginCompatibility->getExtensionsToDeactivate(new Version(), Context::createDefaultContext());

        static::assertCount(1, $extensionStructs);
        static::assertSame('TestApp', $extensionStructs[0]->getName());
    }

    public function testExtensionsToDeactivateOnlyInCompatible(): void
    {
        $pluginCompatibility = new ExtensionCompatibility(
            $this->getStoreClient(__DIR__ . './../_fixtures/responses/extension-green.json'),
            $this->getExtensionDataProvider()
        );

        $extensionStructs = $pluginCompatibility->getExtensionsToDeactivate(new Version(), Context::createDefaultContext());

        static::assertCount(0, $extensionStructs);
    }

    public function getExtensionDataProvider(): AbstractExtensionDataProvider&MockObject
    {
        $extension = new ExtensionStruct();
        $extension->setName('TestApp');
        $extension->setActive(true);

        $extensionDataProvider = $this->createMock(AbstractExtensionDataProvider::class);
        $extensionDataProvider
            ->method('getInstalledExtensions')
            ->willReturn(new ExtensionCollection(['TestApp' => $extension]));

        return $extensionDataProvider;
    }

    public function getStoreClient(string $file = __DIR__ . './../_fixtures/responses/extension-red.json'): StoreClient&MockObject
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('getExtensionCompatibilities')->willReturn(json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR));

        return $storeClient;
    }
}
