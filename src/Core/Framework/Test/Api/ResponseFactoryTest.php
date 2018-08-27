<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ResponseTypeRegistryTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

    /**
     * @var ResponseFactoryRegistry
     */
    private $responseRegistry;

    protected function setUp()
    {
        $this->responseRegistry = $this->getContainer()->get(ResponseFactoryRegistry::class);
    }

    public function getAdminContext(): Context
    {
        $sourceContext = new SourceContext(SourceContext::ORIGIN_API);

        return new Context(Defaults::TENANT_ID, $sourceContext, [Defaults::CATALOG], [], Defaults::CURRENCY, Defaults::LANGUAGE);
    }

    public function testAdminApi()
    {
        $id = Uuid::uuid4()->getHex();
        $accept = 'application/json';
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, '/api/v1/category/' . $id, 1, $accept, false);

        $this->assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);
        $this->assertEquals($id, $content['data']['name']);
    }

    public function testAdminJsonApi()
    {
        $id = Uuid::uuid4()->getHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/v1/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, 1, $accept, false);

        $this->assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        $this->assertEquals($id, $content['data']['attributes']['name']);
        $this->assertEquals($self, $content['links']['self']);
        $this->assertEquals($self, $content['data']['links']['self']);
    }

    public function testAdminJsonApiDefault()
    {
        $id = Uuid::uuid4()->getHex();
        $accept = '*/*';
        $self = 'http://localhost/api/v1/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, 1, $accept, false);

        $this->assertEquals('application/vnd.api+json', $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        $this->assertEquals($id, $content['data']['attributes']['name']);
        $this->assertEquals($self, $content['links']['self']);
        $this->assertEquals($self, $content['data']['links']['self']);
    }

    public function testAdminApiUnsupportedContentType()
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $id = Uuid::uuid4()->getHex();
        $accept = 'text/plain';
        $self = 'http://localhost/api/v1/category/' . $id;
        $context = $this->getAdminContext();
        $this->getDetailResponse($context, $id, $self, 1, $accept, false);
    }

    public function testStorefrontApi()
    {
        $id = Uuid::uuid4()->getHex();
        $accept = 'application/json';
        $context = $this->getStorefrontContext();
        $response = $this->getDetailResponse($context, $id, '/storefront-api/category/' . $id, '', $accept, false);

        $this->assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);
        $this->assertEquals($id, $content['data']['name']);
    }

    public function testStorefrontJsonApi()
    {
        // jsonapi support for storefront is deactivated
        $this->expectException(UnsupportedMediaTypeHttpException::class);

        $id = Uuid::uuid4()->getHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/storefront-api/category/' . $id;
        $context = $this->getStorefrontContext();
        $response = $this->getDetailResponse($context, $id, $self, '', $accept, false);

        $this->assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        $this->assertEquals($id, $content['data']['attributes']['name']);
        $this->assertEquals($self, $content['links']['self']);
        $this->assertEquals($self, $content['data']['links']['self']);

        $this->assertEmptyRelationships($content);
    }

    public function testStorefrontDefaultContentType()
    {
        $id = Uuid::uuid4()->getHex();
        $accept = '*/*';
        $self = 'http://localhost/storefront-api/category/' . $id;
        $context = $this->getStorefrontContext();
        $response = $this->getDetailResponse($context, $id, $self, '', $accept, false);

        $this->assertEquals('application/json', $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);
        $this->assertEquals($id, $content['data']['name']);
    }

    public function testStorefrontApiUnsupportedContentType()
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $id = Uuid::uuid4()->getHex();
        $accept = 'text/plain';
        $self = 'http://localhost/storefront-api/category/' . $id;
        $context = $this->getStorefrontContext();
        $this->getDetailResponse($context, $id, $self, '', $accept, false);
    }

    public function testStorefrontJsonApiList()
    {
        // jsonapi support for storefront is deactivated
        $this->expectException(UnsupportedMediaTypeHttpException::class);

        $id = Uuid::uuid4()->getHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/storefront-api/category';
        $context = $this->getStorefrontContext();
        $response = $this->getListResponse($context, $id, $self, '', $accept);

        $this->assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        $this->assertNotEmpty($content['data']);
        $this->assertEquals($id, $content['data'][0]['attributes']['name']);
        $this->assertEquals($self, $content['links']['self']);
        $this->assertEquals($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    public function testAdminJsonApiList()
    {
        $id = Uuid::uuid4()->getHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/v1/category';
        $context = $this->getAdminContext();
        $response = $this->getListResponse($context, $id, $self, 1, $accept);

        $this->assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        $this->assertNotEmpty($content['data']);
        $this->assertEquals($id, $content['data'][0]['attributes']['name']);
        $this->assertEquals($self, $content['links']['self']);
        $this->assertEquals($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    protected function assertEmptyRelationships($content)
    {
        $this->assertEmpty($content['data']['relationships']);

        if (isset($content['included'])) {
            foreach ($content['included'] as $inc) {
                $this->assertEmpty($inc['relationships']);
            }
        }
    }

    protected function assertDetailJsonApiStructure($content)
    {
        $this->assertArrayHasKey('data', $content);
        $this->assertArrayHasKey('links', $content);
        $this->assertArrayHasKey('included', $content);
    }

    private function getStorefrontContext(): Context
    {
        $sourceContext = new SourceContext(SourceContext::ORIGIN_STOREFRONT_API);

        return new Context(Defaults::TENANT_ID, $sourceContext, [Defaults::CATALOG], [], Defaults::CURRENCY, Defaults::LANGUAGE);
    }

    private function getDetailResponse(Context $context, $id, $path, $version = '', $accept, $setLocationHeader)
    {
        $category = $this->getTestCategory($id);

        $definition = CategoryDefinition::class;
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $this->setVersionHack($request, $version);
        $this->setOrigin($request, $context);

        return $this->getFactory($request)->createDetailResponse($category, $definition, $request, $context, $setLocationHeader);
    }

    private function getListResponse($context, $id, $path, $version = '', $accept)
    {
        $category = $this->getTestCategory($id);

        $col = new EntityCollection([$category]);
        $searchResult = new EntitySearchResult(1, $col, null, new Criteria(), $context);

        $definition = CategoryDefinition::class;
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $this->setVersionHack($request, $version);
        $this->setOrigin($request, $context);

        return $this->getFactory($request)->createListingResponse($searchResult, $definition, $request, $context);
    }

    private function getTestCategory($id)
    {
        $category = new CategoryStruct();
        $category->setId($id);
        $category->setTenantId(Defaults::TENANT_ID);
        $category->setName($id);

        $catalog = new CatalogStruct();
        $catalog->setName('Testkatalog');
        $catalog->setTenantId(Defaults::TENANT_ID);
        $catalog->setId($id);

        $category->setCatalog($catalog);

        return $category;
    }

    private function setVersionHack(Request $request, $version)
    {
        if ($version) {
            $this->setRequestAttributeHack($request, 'version', $version);
        }
    }

    private function setOrigin(Request $request, Context $context)
    {
        $this->setRequestAttributeHack($request, PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function setRequestAttributeHack(Request $request, $key, $value)
    {
        $r = new ReflectionProperty(Request::class, 'attributes');
        $r->setAccessible(true);
        /** @var ParameterBag $attributes */
        $attributes = $r->getValue($request);
        $attributes->set($key, $value);
    }

    private function getFactory(Request $request): ResponseFactoryInterface
    {
        return $this->responseRegistry->getType($request);
    }
}
