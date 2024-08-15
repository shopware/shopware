<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\ExtensionListingLoader;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Store\Struct\StoreUpdateStruct;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ExtensionListingLoader::class)]
class ExtensionListingLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $localExtensions = new ExtensionCollection();
        $localExtensions->set('local-app', $this->createExtension('local-app'));

        $storeExtensions = new ExtensionCollection();
        $storeExtensions->set('local-app', $this->createExtension('local-app', ['label' => 'new-local-label']));
        $storeExtensions->set('store-app', $this->createExtension('store-app', ['inAppFeaturesAvailable' => true, 'label' => 'store-label']));

        $client = $this->createMock(StoreClient::class);
        $client
            ->expects(static::once())
            ->method('listMyExtensions')
            ->willReturn($storeExtensions);

        $loader = new ExtensionListingLoader($client);
        $context = Context::createDefaultContext(new SystemSource());

        $result = $loader->load($localExtensions, $context);

        static::assertCount(2, $result);
        static::assertTrue($result->has('local-app'));
        static::assertTrue($result->has('store-app'));

        $localApp = $result->get('local-app');
        $storeApp = $result->get('store-app');

        static::assertEquals('new-local-label', $localApp->getLabel());
        static::assertEquals('store-label', $storeApp->getLabel());
        static::assertTrue($storeApp->isInAppFeaturesAvailable());
    }

    public function testUpdateInformationIsAdded(): void
    {
        $localExtensions = new ExtensionCollection();
        $localExtensions->set('local-app', $this->createExtension('local-app'));

        $update = new StoreUpdateStruct();
        $update->assign(['name' => 'local-app', 'label' => 'new-local-label', 'version' => '1.0.1']);

        $client = $this->createMock(StoreClient::class);
        $client
            ->expects(static::once())
            ->method('getExtensionUpdateList')
            ->willReturn([$update]);

        $loader = new ExtensionListingLoader($client);
        $context = Context::createDefaultContext(new SystemSource());

        $result = $loader->load($localExtensions, $context);

        static::assertCount(1, $result);

        $localApp = $result->get('local-app');

        static::assertNotNull($localApp);

        static::assertSame('1.0.1', $localApp->getLatestVersion());
        static::assertSame(ExtensionStruct::SOURCE_STORE, $localApp->getUpdateSource());
    }

    public function testExternalApiNotCalledDueToIntegration(): void
    {
        $collection = new ExtensionCollection();
        $collection->set('SwagApp', (new ExtensionStruct())->assign(['name' => 'SwagApp', 'label' => 'Label', 'version' => '1.0.0', 'active' => true, 'type' => 'app']));
        $context = Context::createDefaultContext(new AdminApiSource(null, Uuid::randomHex()));

        $client = $this->createMock(StoreClient::class);
        $client->expects(static::never())->method('getExtensionUpdateList');
        $client->expects(static::never())->method('listMyExtensions');
        $loader = new ExtensionListingLoader($client);

        $loader->load($collection, $context);
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function createExtension(string $name, array $additionalData = []): ExtensionStruct
    {
        $default = [
            'name' => $name,
            'label' => 'Label',
            'version' => '1.0.0',
            'active' => true,
            'type' => 'app',
        ];

        return (new ExtensionStruct())->assign(\array_merge($default, $additionalData));
    }
}
