<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\ExtensionListingLoader;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ExtensionListingLoader::class)]
class ExtensionListingLoaderTest extends TestCase
{
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
}
