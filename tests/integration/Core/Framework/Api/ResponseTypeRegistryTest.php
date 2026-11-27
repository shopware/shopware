<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ResponseTypeRegistryTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private ResponseFactoryRegistry $responseRegistry;

    protected function setUp(): void
    {
        $this->responseRegistry = static::getContainer()->get(ResponseFactoryRegistry::class);
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

        static::assertSame($accept, $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($id, $content['data']['name']);
    }

    public function testAdminJsonApi(): void
    {
        $id = Uuid::randomHex();
        $accept = 'application/vnd.api+json';
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, $accept, false);

        static::assertSame($accept, $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertSame($id, $content['data']['attributes']['name']);
        static::assertSame($self, $content['links']['self']);
        static::assertSame($self, $content['data']['links']['self']);
    }

    #[DataProvider('defaultAcceptHeaders')]
    public function testAdminJsonApiDefault(?string $accept): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $id = Uuid::randomHex();
        $self = 'http://localhost/api/category/' . $id;
        $context = $this->getAdminContext();
        $response = $this->getDetailResponse($context, $id, $self, $accept, false);

        static::assertSame('application/vnd.api+json', $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertSame($id, $content['data']['attributes']['name']);
        static::assertSame($self, $content['links']['self']);
        static::assertSame($self, $content['data']['links']['self']);
    }

    #[DataProvider('defaultAcceptHeaders')]
    public function testAdminPlainJsonDefault(?string $accept): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $id = Uuid::randomHex();
        $response = $this->getDetailResponse($this->getAdminContext(), $id, '/api/category/' . $id, $accept, false);

        static::assertSame('application/json', $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($id, $data['data']['name']);
        static::assertArrayNotHasKey('attributes', $data['data']);

        $response = $this->getListResponse($this->getAdminContext(), $id, '/api/category', $accept);

        static::assertSame('application/json', $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($id, $data['data'][0]['name']);
        static::assertSame(1, $data['total']);
        static::assertSame([], $data['aggregations']);
    }

    public static function defaultAcceptHeaders(): \Generator
    {
        yield 'wildcard' => ['*/*'];
        yield 'missing Accept header' => [null];
    }

    #[DataProvider('explicitAcceptHeaders')]
    public function testExplicitFormatTakesPrecedenceOverDefault(string $accept, string $expectedContentType): void
    {
        $id = Uuid::randomHex();
        $response = $this->getDetailResponse($this->getAdminContext(), $id, '/api/category/' . $id, $accept, false);

        static::assertSame($expectedContentType, $response->headers->get('content-type'));
    }

    public static function explicitAcceptHeaders(): \Generator
    {
        yield 'JSON API before wildcard' => ['application/vnd.api+json, */*;q=0.5', 'application/vnd.api+json'];
        yield 'JSON before wildcard' => ['application/json, */*;q=0.5', 'application/json'];
        yield 'JSON API preferred by quality' => ['application/json;q=0.5, application/vnd.api+json', 'application/vnd.api+json'];
        yield 'JSON preferred by quality' => ['application/vnd.api+json;q=0.5, application/json', 'application/json'];
    }

    public function testAdminApiUnsupportedContentType(): void
    {
        $id = Uuid::randomHex();
        $accept = 'text/plain';
        $this->expectExceptionObject(ApiException::unsupportedResponseMediaTypes([$accept]));
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

        static::assertSame($accept, $response->headers->get('content-type'));
        $content = $response->getContent();
        static::assertIsString($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertDetailJsonApiStructure($content);
        static::assertIsArray($content['data']);
        static::assertNotCount(0, $content['data']);
        static::assertSame($id, $content['data'][0]['attributes']['name']);
        static::assertSame($self, $content['links']['self']);
        static::assertSame($self . '/' . $id, $content['data'][0]['links']['self']);
    }

    /**
     * @param array<string, mixed> $content
     */
    protected function assertDetailJsonApiStructure(array $content): void
    {
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('links', $content);
        static::assertArrayHasKey('included', $content);
    }

    private function getDetailResponse(Context $context, string $id, string $path, ?string $accept, bool $setLocationHeader): Response
    {
        $category = $this->getTestCategory($id);

        $definition = static::getContainer()->get(CategoryDefinition::class);
        $request = Request::create($path, 'GET', [], [], [], $accept === null ? [] : ['HTTP_ACCEPT' => $accept]);
        if ($accept === null) {
            $request->headers->remove('Accept');
        }
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $this->getFactory($request)->createDetailResponse(new Criteria(), $category, $definition, $request, $context, $setLocationHeader);
    }

    private function getListResponse(Context $context, string $id, string $path, ?string $accept): Response
    {
        $category = $this->getTestCategory($id);

        $col = new CategoryCollection([$category]);
        $criteria = new Criteria();
        /** @var EntitySearchResult<CategoryCollection> */
        $searchResult = new EntitySearchResult('category', 1, $col, null, $criteria, $context);

        $definition = static::getContainer()->get(CategoryDefinition::class);
        $request = Request::create($path, 'GET', [], [], [], $accept === null ? [] : ['HTTP_ACCEPT' => $accept]);
        if ($accept === null) {
            $request->headers->remove('Accept');
        }
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $this->getFactory($request)->createListingResponse($criteria, $searchResult, $definition, $request, $context);
    }

    private function getTestCategory(string $id): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setName($id);
        $category->internalSetEntityData('category', new FieldVisibility([]));

        return $category;
    }

    private function getFactory(Request $request): ResponseFactoryInterface
    {
        return $this->responseRegistry->getType($request);
    }
}
