<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\ContentSystem\ResolvedContentLayout;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentRoute::class)]
class ContentRouteTest extends TestCase
{
    private RenderingSpecificationResolver&Stub $specificationResolver;

    private CacheTagCollector&Stub $cacheTagCollector;

    private AbstractResponseFactory&Stub $responseFactory;

    private ContentPipeline&Stub $contentPipeline;

    protected function setUp(): void
    {
        $this->specificationResolver = static::createStub(RenderingSpecificationResolver::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->responseFactory = static::createStub(AbstractResponseFactory::class);
        $this->contentPipeline = static::createStub(ContentPipeline::class);
    }

    #[TestDox('returns content page from pipeline via response factory')]
    public function testLoadReturnsContentPageFromPipeline(): void
    {
        $request = new Request();
        $contentPage = new ContentPage('layout-1', [ContentElementBuilder::create('root')->build()], 'Test', null);

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request));
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($contentPage);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($contentPage));

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $result = $route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertInstanceOf(ContentRouteResponse::class, $result);
        static::assertSame($contentPage, $result->getContentPage());
    }

    #[TestDox('collects layout and specification cache tags')]
    public function testLoadCollectsLayoutAndSpecificationCacheTags(): void
    {
        $request = new Request();
        $contentPage = new ContentPage('layout-1', [ContentElementBuilder::create('root')->build()], 'Test', null);

        $collectedTags = [];
        $this->cacheTagCollector->method('addTag')
            ->willReturnCallback(function (string ...$tags) use (&$collectedTags): void {
                array_push($collectedTags, ...$tags);
            });

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request, ['product-abc']));
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($contentPage);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($contentPage));

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertContains('content-layout-layout-1', $collectedTags);
        static::assertContains('product-abc', $collectedTags);
    }

    #[TestDox('finalizes cache context on request')]
    public function testLoadFinalizesCacheContextOnRequest(): void
    {
        $request = new Request();
        $contentPage = new ContentPage('layout-1', [ContentElementBuilder::create('root')->build()], 'Test', null);

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request));
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($contentPage);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($contentPage));

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertNull($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }

    #[TestDox('throws layout not found when the resolved layout does not exist')]
    public function testLoadThrowsLayoutNotFoundWhenLayoutDoesNotExist(): void
    {
        $request = new Request();

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request));

        $route = $this->createRoute($this->createLayoutRepository());

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('layout-1'));

        $route->load('/product/abc', $request, Generator::generateSalesChannelContext());
    }

    #[TestDox('throws DecorationPatternException from getDecorated')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentRoute::class));

        $this->createRoute($this->createLayoutRepository())->getDecorated();
    }

    /**
     * @param StaticEntityRepository<ContentLayoutCollection> $repository
     */
    private function createRoute(StaticEntityRepository $repository): ContentRoute
    {
        return new ContentRoute(
            $this->specificationResolver,
            ContentSection::MAIN,
            $this->cacheTagCollector,
            $repository,
            $this->responseFactory,
            $this->contentPipeline,
            new CacheFinalizer($this->cacheTagCollector),
        );
    }

    /**
     * @param list<string> $cacheTags
     */
    private function createResolved(Request $request, array $cacheTags = []): ResolvedContentLayout
    {
        return ResolvedContentLayout::create(
            'layout-1',
            new RenderingSpecification([], PlaceholderValues::from([]), $request, null, $cacheTags),
        );
    }

    /**
     * @return StaticEntityRepository<ContentLayoutCollection>
     */
    private function createLayoutRepository(ContentLayoutEntity ...$entities): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([$entities]);

        return $repository;
    }

    private function createLayoutEntity(string $id = 'layout-1', string $name = 'Test'): ContentLayoutEntity
    {
        $entity = new ContentLayoutEntity();
        $entity->setId($id);
        $entity->setName($name);
        $entity->setVersion('1.0');
        $entity->setLayout([ContentElementBuilder::create('root')->build()]);

        return $entity;
    }
}
