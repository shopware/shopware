<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\ContentSystem\ResolvedContentLayout;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('returns content page from pipeline and collects layout and specification cache tags')]
    public function testLoadReturnsContentPageAndCollectsCacheTags(): void
    {
        $request = new Request();
        $root = new RenderedElement('root', 'section');
        $renderResult = $this->createRenderResult([$root]);

        $collectedTags = [];
        $this->cacheTagCollector->method('addTag')
            ->willReturnCallback(function (string ...$tags) use (&$collectedTags): void {
                array_push($collectedTags, ...$tags);
            });

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request, ['product-abc']));
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($renderResult);
        $this->responseFactory->method('createResponse')
            ->willReturnCallback(fn (RenderResult $passed): ContentRouteResponse => new ContentRouteResponse($passed));

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $result = $route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertInstanceOf(ContentRouteResponse::class, $result);
        static::assertSame('layout-1', $result->getContentPage()->id);
        static::assertSame([$root], $result->getContentPage()->elements);
        static::assertContains('content-layout-layout-1', $collectedTags);
        static::assertContains('product-abc', $collectedTags);
    }

    #[TestDox('marks the request uncacheable when the pipeline disables the cache context')]
    public function testLoadDisablesHttpCacheWhenPipelineDisablesCacheContext(): void
    {
        $request = new Request();

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request));
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturnCallback(
            function (RenderableLayout $layout, RenderingSpecification $specification, RenderingCacheContext $cacheContext): RenderResult {
                $cacheContext->disable();

                return $this->createRenderResult();
            }
        );
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($this->createRenderResult()));

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertFalse($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }

    #[TestDox('hands the pipeline both answers the format gives: its rendering mode and whether it collects a value index')]
    public function testLoadPassesTheFormatsModeAndIndexCollectionToThePipeline(): void
    {
        $request = new Request();

        $this->specificationResolver->method('resolve')->willReturn($this->createResolved($request));
        // Deliberately an unusual pair no shipped format uses, so a route reading one answer off the other
        // cannot pass: the two questions are independent.
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::SKELETON);
        $this->responseFactory->method('collectsValueIndex')->willReturn(true);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($this->createRenderResult()));

        $observed = [];
        $this->contentPipeline->method('load')->willReturnCallback(
            function (RenderableLayout $layout, RenderingSpecification $specification, RenderingCacheContext $cacheContext, RenderingMode $mode, bool $collectValueIndex) use (&$observed): RenderResult {
                $observed = [$mode, $collectValueIndex];

                return $this->createRenderResult();
            }
        );

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertSame([RenderingMode::SKELETON, true], $observed);
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

    /**
     * @param 'attributes'|'query'|'request' $bag
     */
    #[DataProvider('fieldSelectionProvider')]
    #[TestDox('rejects a $parameter parameter arriving in the $bag bag, naming it, before specification resolution runs')]
    public function testLoadRejectsFieldSelectionFromEveryBag(string $bag, string $parameter): void
    {
        $request = new Request();
        $request->{$bag}->set($parameter, ['content_page' => ['elements']]);

        // The refusal has to happen at admission: a resolver that runs at all fails the test rather than
        // letting a later throw stand in for the early one.
        $this->specificationResolver->method('resolve')
            ->willThrowException(new \LogicException('Specification resolution must not run for a rejected request.'));

        $route = $this->createRoute($this->createLayoutRepository($this->createLayoutEntity()));

        $this->expectExceptionObject(ContentSystemException::fieldSelectionNotSupported($parameter));

        $route->load('/product/abc', $request, Generator::generateSalesChannelContext());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fieldSelectionProvider(): iterable
    {
        yield 'includes in attributes' => ['attributes', 'includes'];
        yield 'includes in query' => ['query', 'includes'];
        yield 'includes in request' => ['request', 'includes'];
        yield 'excludes in attributes' => ['attributes', 'excludes'];
        yield 'excludes in query' => ['query', 'excludes'];
        yield 'excludes in request' => ['request', 'excludes'];
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
     * @param list<RenderedElement> $tree
     */
    private function createRenderResult(array $tree = []): RenderResult
    {
        return new RenderResult($tree, LayoutReference::create('layout-1', 'Test', null), null);
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
        $entity->setLayout([StoredElementBuilder::create('root')->build()]);

        return $entity;
    }
}
