<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\ExtensionListingLoader;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ExtensionListingLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    /**
     * @var ExtensionListingLoader
     */
    private $extensionListingLoader;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        parent::setUp();
        $this->extensionListingLoader = $this->getContainer()->get(ExtensionListingLoader::class);
    }

    public function testServerNotReachable(): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(function (): void {
            throw new ClientException('', new Request('GET', ''), new Response(500, [], ''));
        });

        $collection = new ExtensionCollection();
        $collection->set('myPlugin', (new ExtensionStruct())->assign(['name' => 'myPlugin', 'label' => 'Label', 'version' => '1.0.0']));
        $collection = $this->extensionListingLoader->load($collection, $this->createAdminStoreContext());

        static::assertSame(1, $collection->count());
    }

    public function testExternalAreAdded(): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], '{"data":[]}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json')));

        $collection = new ExtensionCollection();
        $collection->set('myPlugin', (new ExtensionStruct())->assign(['name' => 'myPlugin', 'label' => 'Label', 'version' => '1.0.0', 'active' => true]));
        $collection->set('myPlugin2', (new ExtensionStruct())->assign(['name' => 'myPlugin2', 'label' => 'Label', 'version' => '1.0.0', 'installedAt' => new \DateTime()]));
        $collection = $this->extensionListingLoader->load($collection, $this->createAdminStoreContext());

        static::assertSame('app', $collection->get('SwagApp')->getType());
        static::assertSame('store', $collection->get('SwagApp')->getSource());
        static::assertSame(8, $collection->count());
    }

    public function testExternalAreMerged(): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], '{"data":[]}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json')));

        $collection = new ExtensionCollection();
        $collection->set('SwagApp', (new ExtensionStruct())->assign(['name' => 'SwagApp', 'label' => 'Label', 'version' => '1.0.0', 'active' => true, 'type' => 'app']));
        $collection = $this->extensionListingLoader->load($collection, $this->createAdminStoreContext());

        static::assertSame('app', $collection->get('SwagApp')->getType());
        static::assertSame('local', $collection->get('SwagApp')->getSource());
        static::assertSame('Description', $collection->get('SwagApp')->getDescription());
        static::assertSame('Short Description', $collection->get('SwagApp')->getShortDescription());
        static::assertSame('2.0.0', $collection->get('SwagApp')->getLatestVersion());
        static::assertSame(6, $collection->count());
    }
}
