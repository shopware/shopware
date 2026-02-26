<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Content\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Content\ContentSystem\ContentPipeline;
use Shopware\Core\Content\ContentSystem\ContentSection;
use Shopware\Core\Content\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentRoute;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
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

    private ContentRoute $route;

    protected function setUp(): void
    {
        $this->specificationResolver = static::createStub(RenderingSpecificationResolver::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->responseFactory = static::createStub(AbstractResponseFactory::class);
        $this->contentPipeline = static::createStub(ContentPipeline::class);

        $this->route = new ContentRoute(
            $this->specificationResolver,
            ContentSection::MAIN,
            $this->cacheTagCollector,
            $this->responseFactory,
            $this->contentPipeline,
            new CacheFinalizer($this->cacheTagCollector),
        );
    }

    #[TestDox('returns content page from pipeline via response factory')]
    public function testLoadReturnsContentPageFromPipeline(): void
    {
        $request = new Request();
        $contentPage = new ContentPage('layout-1', [ContentElementBuilder::create('root')->build()], 'Test', null);

        $this->specificationResolver->method('resolve')->willReturn(
            new RenderingSpecification('layout-1', [], PlaceholderValues::from([]), $request, null, [])
        );
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($contentPage);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($contentPage));

        $result = $this->route->load('/product/abc', $request, Generator::generateSalesChannelContext());

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

        $this->specificationResolver->method('resolve')->willReturn(
            new RenderingSpecification('layout-1', [], PlaceholderValues::from([]), $request, null, ['product-abc'])
        );
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($contentPage);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($contentPage));

        $this->route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertContains('content-layout-layout-1', $collectedTags);
        static::assertContains('product-abc', $collectedTags);
    }

    #[TestDox('finalizes cache context on request')]
    public function testLoadFinalizesCacheContextOnRequest(): void
    {
        $request = new Request();
        $contentPage = new ContentPage('layout-1', [ContentElementBuilder::create('root')->build()], 'Test', null);

        $this->specificationResolver->method('resolve')->willReturn(
            new RenderingSpecification('layout-1', [], PlaceholderValues::from([]), $request, null, [])
        );
        $this->responseFactory->method('getRenderingMode')->willReturn(RenderingMode::FULL);
        $this->contentPipeline->method('load')->willReturn($contentPage);
        $this->responseFactory->method('createResponse')->willReturn(new ContentRouteResponse($contentPage));

        $this->route->load('/product/abc', $request, Generator::generateSalesChannelContext());

        static::assertNull($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }

    #[TestDox('throws DecorationPatternException from getDecorated')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentRoute::class));

        $this->route->getDecorated();
    }
}
