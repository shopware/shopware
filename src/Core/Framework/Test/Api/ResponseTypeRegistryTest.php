<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ResponseTypeRegistryTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var ResponseFactoryRegistry
     */
    private $responseRegistry;

    protected function setUp(): void
    {
        $this->responseRegistry = $this->getContainer()->get(ResponseFactoryRegistry::class);
    }

    public function getAdminContext(): Context
    {
        return new Context(new AdminApiSource(Uuid::randomHex()));
    }

    public function testAdminApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/json';
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, '/api/v1/category/' . $id, 1, $accept, false);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);
        static::assertEquals($id, $content['data']['name']);
    }

    public function testAdminJsonApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/v1/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, 1, $accept, false);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        static::assertEquals($id, $content['data']['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self, $content['data']['links']['self']);
    }

    public function testAdminJsonApiDefault(): void
    {
        $id = Uuid::randomHex();
        $accept = '*/*';
        $self = 'http://localhost/api/v1/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, 1, $accept, false);

        static::assertEquals('application/vnd.api+json', $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        static::assertEquals($id, $content['data']['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self, $content['data']['links']['self']);
    }

    public function testAdminApiUnsupportedContentType(): void
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $id = Uuid::randomHex();
        $accept = 'text/plain';
        $self = 'http://localhost/api/v1/category/' . $id;
        $context = $this->getAdminContext();
        $this->getDetailResponse($context, $id, $self, 1, $accept, false);
    }

    public function testSalesChannelApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/json';
        $context = $this->getSalesChannelContext();
        $response = $this->getDetailResponse($context, $id, '/sales-channel-api/category/' . $id, '', $accept, false);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);
        static::assertEquals($id, $content['data']['name']);
    }

    public function testSalesChannelJsonApi(): void
    {
        // jsonapi support for sales channel is deactivated
        $this->expectException(UnsupportedMediaTypeHttpException::class);

        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/sales-channel-api/category/' . $id;
        $context = $this->getSalesChannelContext();
        $response = $this->getDetailResponse($context, $id, $self, '', $accept, false);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        static::assertEquals($id, $content['data']['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self, $content['data']['links']['self']);

        $this->assertEmptyRelationships($content);
    }

    public function testSSalesChannelDefaultContentType(): void
    {
        $id = Uuid::randomHex();
        $accept = '*/*';
        $self = 'http://localhost/sales-channel-api/category/' . $id;
        $context = $this->getSalesChannelContext();
        $response = $this->getDetailResponse($context, $id, $self, '', $accept, false);

        static::assertEquals('application/json', $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);
        static::assertEquals($id, $content['data']['name']);
    }

    public function testSalesChannelApiUnsupportedContentType(): void
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $id = Uuid::randomHex();
        $accept = 'text/plain';
        $self = 'http://localhost/sales-channel-api/category/' . $id;
        $context = $this->getSalesChannelContext();
        $this->getDetailResponse($context, $id, $self, '', $accept, false);
    }

    public function testSalesChannelJsonApiList(): void
    {
        // jsonapi support for storefront is deactivated
        $this->expectException(UnsupportedMediaTypeHttpException::class);

        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/sales-channel-api/category';
        $context = $this->getSalesChannelContext();
        $response = $this->getListResponse($context, $id, $self, '', $accept);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($id, $content['data'][0]['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    public function testAdminJsonApiList(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/v1/category';
        $context = $this->getAdminContext();
        $response = $this->getListResponse($context, $id, $self, 1, $accept);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true);

        $this->assertDetailJsonApiStructure($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($id, $content['data'][0]['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    protected function assertEmptyRelationships($content): void
    {
        static::assertEmpty($content['data']['relationships']);

        if (isset($content['included'])) {
            foreach ($content['included'] as $inc) {
                static::assertEmpty($inc['relationships']);
            }
        }
    }

    protected function assertDetailJsonApiStructure($content): void
    {
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('links', $content);
        static::assertArrayHasKey('included', $content);
    }

    private function getSalesChannelContext(): Context
    {
        return new Context(new SalesChannelApiSource(Defaults::SALES_CHANNEL));
    }

    private function getDetailResponse(Context $context, $id, $path, $version = '', $accept, $setLocationHeader): Response
    {
        $category = $this->getTestCategory($id);

        $definition = CategoryDefinition::class;
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $this->setVersionHack($request, $version);
        $this->setOrigin($request, $context);

        return $this->getFactory($request)->createDetailResponse($category, $definition, $request, $context, $setLocationHeader);
    }

    private function getListResponse($context, $id, $path, $version = '', $accept): Response
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

    private function getTestCategory($id): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setName($id);

        return $category;
    }

    private function setVersionHack(Request $request, $version): void
    {
        if ($version) {
            $this->setRequestAttributeHack($request, 'version', $version);
        }
    }

    private function setOrigin(Request $request, Context $context): void
    {
        $this->setRequestAttributeHack($request, PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function setRequestAttributeHack(Request $request, $key, $value): void
    {
        $r = new \ReflectionProperty(Request::class, 'attributes');
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
