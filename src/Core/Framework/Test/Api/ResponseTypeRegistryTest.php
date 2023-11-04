<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * @internal
 */
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
        $response = $this->getDetailResponse($context, $id, '/api/category/' . $id, $accept, false);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($id, $content['data']['name']);
    }

    public function testAdminJsonApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, $accept, false);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertEquals($id, $content['data']['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self, $content['data']['links']['self']);
    }

    public function testAdminJsonApiDefault(): void
    {
        $id = Uuid::randomHex();
        $accept = '*/*';
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, $accept, false);

        static::assertEquals('application/vnd.api+json', $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $this->getDetailResponse($context, $id, $self, $accept, false);
    }

    public function testAdminJsonApiList(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/category';
        $context = $this->getAdminContext();
        $response = $this->getListResponse($context, $id, $self, $accept);

        static::assertEquals($accept, $response->headers->get('content-type'));
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertNotEmpty($content['data']);
        static::assertEquals($id, $content['data'][0]['attributes']['name']);
        static::assertEquals($self, $content['links']['self']);
        static::assertEquals($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    protected function assertDetailJsonApiStructure($content): void
    {
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('links', $content);
        static::assertArrayHasKey('included', $content);
    }

    private function getDetailResponse(Context $context, string $id, string $path, string $accept, bool $setLocationHeader): Response
    {
        $category = $this->getTestCategory($id);

        $definition = $this->getContainer()->get(CategoryDefinition::class);
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $this->setOrigin($request, $context);

        return $this->getFactory($request)->createDetailResponse(new Criteria(), $category, $definition, $request, $context, $setLocationHeader);
    }

    private function getListResponse(Context $context, string $id, string $path, string $accept): Response
    {
        $category = $this->getTestCategory($id);

        $col = new EntityCollection([$category]);
        $criteria = new Criteria();
        $searchResult = new EntitySearchResult('product', 1, $col, null, $criteria, $context);

        $definition = $this->getContainer()->get(CategoryDefinition::class);
        $request = Request::create($path, 'GET', [], [], [], ['HTTP_ACCEPT' => $accept]);
        $this->setOrigin($request, $context);

        return $this->getFactory($request)->createListingResponse($criteria, $searchResult, $definition, $request, $context);
    }

    private function getTestCategory($id): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setName($id);
        $category->internalSetEntityData('category', new FieldVisibility([]));

        return $category;
    }

    private function setOrigin(Request $request, Context $context): void
    {
        $this->setRequestAttributeHack($request, PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function setRequestAttributeHack(Request $request, string $key, Context|int $value): void
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
