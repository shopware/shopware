<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageException;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageLoader::class)]
class LandingPageLoaderTest extends TestCase
{
    public function testNoLandingPageIdException(): void
    {
        $landingPageRouteMock = $this->createMock(LandingPageRoute::class);
        $landingPageRouteMock->expects($this->never())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );

        $request = new Request([], [], []);
        $salesChannelContext = $this->getSalesChannelContext();

        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));
        $landingPageLoader->load($request, $salesChannelContext);
    }

    public function testNoLandingPageException(): void
    {
        $landingPageRouteMock = $this->createMock(LandingPageRoute::class);
        $landingPageRouteMock->expects($this->once())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );

        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $expectedException = LandingPageException::notFound($landingPageId);

        // @deprecated tag:v6.8.0 - remove this if block
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedException = new PageNotFoundException($landingPageId);
        }

        $this->expectExceptionObject($expectedException);
        $landingPageLoader->load($request, $salesChannelContext);
    }

    #[DataProvider('cmsPageProvider')]
    public function testLoadsAssignedContentLayout(bool $withCmsPage): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $context = $this->getSalesChannelContext();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setTranslated(['name' => 'Campaign', 'metaTitle' => 'Campaign title']);
        if ($withCmsPage) {
            $landingPage->setCmsPage(new CmsPageEntity());
        }
        $response = new ContentRouteResponse(new RenderResult(
            tree: [],
            reference: LayoutReference::create(Uuid::randomHex(), 'Campaign layout', null),
            index: null,
        ));
        $contentRoute = $this->createMock(AbstractContentRoute::class);
        $contentRoute->expects($this->once())->method('load')
            ->with('/landing-page/' . $landingPageId, $request, $context)
            ->willReturn($response);
        $loader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $context),
            static::createStub(EventDispatcherInterface::class),
            $contentRoute,
        );

        $page = $loader->load($request, $context);

        static::assertSame($response->getContentPage(), $page->getExtension('contentPage'));
        static::assertSame($landingPage, $page->getLandingPage());
        static::assertSame($landingPageId, $page->getNavigationId());
        static::assertSame('Campaign title', $page->getMetaInformation()?->getMetaTitle());
    }

    /**
     * @return \Generator<string, array{bool}>
     */
    public static function cmsPageProvider(): \Generator
    {
        yield 'content layout without legacy CMS' => [false];
        yield 'content layout takes precedence over legacy CMS' => [true];
    }

    public function testFallsBackToCmsWhenNoContentLayoutIsAssigned(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $context = $this->getSalesChannelContext();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage(new CmsPageEntity());
        $contentRoute = static::createStub(AbstractContentRoute::class);
        $contentRoute->method('load')->willThrowException(
            ContentSystemException::layoutAssignmentNotFound('landing_page', $landingPageId, $context->getSalesChannelId())
        );
        $loader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $context),
            static::createStub(EventDispatcherInterface::class),
            $contentRoute,
        );

        $page = $loader->load($request, $context);

        static::assertSame($landingPage, $page->getLandingPage());
        static::assertFalse($page->hasExtension('contentPage'));
    }

    public function testDoesNotHideContentLayoutRenderingErrors(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $context = $this->getSalesChannelContext();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $exception = ContentSystemException::layoutNotFound(Uuid::randomHex());
        $contentRoute = static::createStub(AbstractContentRoute::class);
        $contentRoute->method('load')->willThrowException($exception);
        $loader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $context),
            static::createStub(EventDispatcherInterface::class),
            $contentRoute,
        );

        $this->expectExceptionObject($exception);

        $loader->load($request, $context);
    }

    public function testItLoads(): void
    {
        $productId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $product = $this->getProduct($productId);
        $cmsPage = $this->getCmsPage($product);

        $landingPageLoader = $this->getLandingPageLoaderWithProduct($landingPageId, $cmsPage, $request, $salesChannelContext);

        $page = $landingPageLoader->load($request, $salesChannelContext);

        $cmsPageLoaded = $page->getLandingPage();
        static::assertNotNull($cmsPageLoaded);
        static::assertSame($cmsPage, $cmsPageLoaded->getCmsPage());
        static::assertSame($landingPageId, $page->getNavigationId());
    }

    public function testItLoadsProperPageMetaInformation(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
            'metaTitle' => 'TEST_META_TITLE',
            'metaDescription' => 'TEST_META_DESCRIPTION',
            'keywords' => 'TEST_KEYWORDS',
        ];

        $expected = [
            'metaTitle' => $translated['metaTitle'],
            'metaDescription' => $translated['metaDescription'],
            'metaKeywords' => $translated['keywords'],
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $salesChannelContext);

        $metaInformation = $landingPageLoader->load($request, $salesChannelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    public function testItLoadsProperPageMetaInformationWithNameOnly(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
        ];

        $expected = [
            'metaTitle' => $translated['name'],
            'metaDescription' => '',
            'metaKeywords' => '',
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $salesChannelContext);

        $metaInformation = $landingPageLoader->load($request, $salesChannelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    private function getLandingPageLoaderWithProduct(
        string $landingPageId,
        CmsPageEntity $cmsPage,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): LandingPageLoader {
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage($cmsPage);

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $salesChannelContext),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    /**
     * @param array<string> $translated
     */
    private function getLandingPageLoaderWithTranslated(
        string $landingPageId,
        array $translated,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): LandingPageLoader {
        $productId = Uuid::randomHex();
        $product = $this->getProduct($productId);
        $cmsPage = $this->getCmsPage($product);

        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage($cmsPage);
        $landingPage->setTranslated($translated);
        $landingPage->setName('INCORRECT_NAME');

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $salesChannelContext),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    private function createLandingPageRoute(
        LandingPageEntity $landingPage,
        Request $expectedRequest,
        SalesChannelContext $expectedContext
    ): LandingPageRoute {
        $landingPageRoute = $this->createMock(LandingPageRoute::class);
        $landingPageRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (
                string $landingPageId,
                Request $request,
                SalesChannelContext $context
            ) use (
                $landingPage,
                $expectedRequest,
                $expectedContext
            ): LandingPageRouteResponse {
                static::assertSame($landingPage->getId(), $landingPageId);
                static::assertSame($expectedRequest, $request);
                static::assertSame($expectedContext, $context);

                return new LandingPageRouteResponse($landingPage);
            });

        return $landingPageRoute;
    }

    private function getProduct(string $productId): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }

    private function getCmsPage(SalesChannelProductEntity $productEntity): CmsPageEntity
    {
        $cmsPageEntity = new CmsPageEntity();

        $cmsSectionEntity = new CmsSectionEntity();
        $cmsSectionEntity->setId(Uuid::randomHex());

        $cmsBlockEntity = new CmsBlockEntity();
        $cmsBlockEntity->setId(Uuid::randomHex());

        $cmsSlotEntity = new CmsSlotEntity();
        $cmsSlotEntity->setId(Uuid::randomHex());
        $cmsSlotEntity->setSlot(json_encode($productEntity->getTranslated(), \JSON_THROW_ON_ERROR));

        $cmsBlockEntity->setSlots(new CmsSlotCollection([$cmsSlotEntity]));
        $cmsSectionEntity->setBlocks(new CmsBlockCollection([$cmsBlockEntity]));
        $cmsPageEntity->setSections(new CmsSectionCollection([$cmsSectionEntity]));

        return $cmsPageEntity;
    }
}
