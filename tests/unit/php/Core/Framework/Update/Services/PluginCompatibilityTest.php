<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Update\Services\PluginCompatibility
 */
class PluginCompatibilityTest extends TestCase
{
    /**
     * @dataProvider statusProvider
     */
    public function testGetExtension(string $file, string $statusName, ?string $statusColor): void
    {
        $extension = new ExtensionStruct();
        $extension->setName('TestApp');

        $extensionDataProvider = $this->createMock(AbstractExtensionDataProvider::class);
        $extensionDataProvider
            ->method('getInstalledExtensions')
            ->willReturn(new ExtensionCollection([$extension]));

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->method('getExtensionCompatibilities')->willReturn(json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR));

        $pluginCompatibility = new PluginCompatibility(
            $storeClient,
            $extensionDataProvider
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
    public function statusProvider(): iterable
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
}
