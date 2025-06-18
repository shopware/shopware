<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\NavigationController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedHook;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedHook;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationController::class)]
class NavigationControllerTest extends TestCase
{
    private NavigationPageLoaderInterface&MockObject $pageLoader;

    private MenuOffcanvasPageletLoaderInterface&MockObject $offCanvasLoader;

    private NavigationControllerTestClass $controller;

    private HeaderPageletLoaderInterface&MockObject $headerLoader;

    private FooterPageletLoaderInterface&MockObject $footerLoader;

    private AbstractCategoryUrlGenerator $categoryUrlGenerator;

    private SeoUrlPlaceholderHandlerInterface $seoUrlReplacer;

    protected function setUp(): void
    {
        $this->pageLoader = $this->createMock(NavigationPageLoaderInterface::class);
        $this->offCanvasLoader = $this->createMock(MenuOffcanvasPageletLoaderInterface::class);
        $this->headerLoader = $this->createMock(HeaderPageletLoaderInterface::class);
        $this->footerLoader = $this->createMock(FooterPageletLoaderInterface::class);

        $this->seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandler::class);
        $this->seoUrlReplacer->method('replace')
            ->willReturnCallback(fn (string $url) => $url);
        $this->seoUrlReplacer->method('generate')
            ->willReturnCallback(function (string $route, array $parameters) {
                return match ($route) {
                    'frontend.detail.page' => '/product/' . $parameters['productId'],
                    'frontend.navigation.page' => '/navigation/' . $parameters['navigationId'],
                    'frontend.home.page' => '/',
                    default => '/' . $route,
                };
            });
        $this->categoryUrlGenerator = new CategoryUrlGenerator($this->seoUrlReplacer);

        $this->controller = new NavigationControllerTestClass(
            $this->pageLoader,
            $this->offCanvasLoader,
            $this->headerLoader,
            $this->footerLoader,
            $this->categoryUrlGenerator,
            $this->seoUrlReplacer,
        );
    }

    public function testHomeRendersStorefront(): void
    {
        $this->pageLoader->method('load')
            ->willReturn(new NavigationPage());

        $request = new Request();
        $context = Generator::generateSalesChannelContext();

        $this->controller->home($request, $context);
        static::assertSame('@Storefront/storefront/page/content/index.html.twig', $this->controller->renderStorefrontView);
    }

    public function testIndexRendersStorefront(): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_PAGE);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request([
            'navigationId' => Uuid::randomHex(),
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->controller->index($context, $request);
        static::assertSame('@Storefront/storefront/page/content/index.html.twig', $this->controller->renderStorefrontView);
    }

    public static function redirectOnLinkTypeDataProvider(): \Generator
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        yield 'product link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
                'internalLink' => $productId,
                'externalLink' => 'This should not be used',
            ],
            'expectedUrl' => '/product/' . $productId,
        ];

        yield 'category link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'internalLink' => $categoryId,
                'externalLink' => 'This should not be used',
            ],
            'expectedUrl' => '/navigation/' . $categoryId,
        ];

        yield 'external link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'internalLink' => 'This should not be used',
                'externalLink' => 'https://example.com',
            ],
            'expectedUrl' => 'https://example.com',
        ];
    }

    /**
     * @param array{linkType: string, internalLink: string, externalLink: string} $data
     */
    #[DataProvider('redirectOnLinkTypeDataProvider')]
    public function testIndexRedirectsOnLinkType(array $data, string $expectedUrl): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType($data['linkType']);
        $category->setInternalLink($data['internalLink']);
        $category->setExternalLink($data['externalLink']);

        $category->setTranslated([
            'linkType' => $data['linkType'],
            'internalLink' => $data['internalLink'],
            'externalLink' => $data['externalLink'],
        ]);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request(
            ['navigationId' => Uuid::randomHex()],
            [],
            [RequestTransformer::STOREFRONT_URL => 'https://example.com'],
        );

        $context = Generator::generateSalesChannelContext();

        $response = $this->controller->index($context, $request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame($expectedUrl, $response->getTargetUrl());
    }

    public function testIndexDoesNotRedirectOnLinkTypeWithoutUrl(): void
    {
        $categoryId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId($categoryId);
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_PRODUCT);
        $category->setInternalLink(null);

        $category->setTranslated([
            'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
            'internalLink' => null,
        ]);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request(
            ['navigationId' => Uuid::randomHex()],
            [],
            [RequestTransformer::STOREFRONT_URL => 'https://example.com'],
        );

        $context = Generator::generateSalesChannelContext();

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage(\sprintf(
            'Category "%s" not found.',
            $categoryId,
        ));

        $this->controller->index($context, $request);
    }

    public function testOffcanvasRendersStorefront(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();

        $response = $this->controller->offcanvas($request, $context);
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertSame('@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig', $this->controller->renderStorefrontView);
    }

    public function testHeaderRendersStorefront(): void
    {
        $request = new Request(['headerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateSalesChannelContext();
        $headerPagelet = new HeaderPagelet(new Tree(null, []), new LanguageCollection(), new CurrencyCollection());

        $this->headerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($headerPagelet);

        $this->controller->header($request, $context);
        static::assertSame('@Storefront/storefront/layout/header.html.twig', $this->controller->renderStorefrontView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderStorefrontParameters['headerParameters']);

        static::assertInstanceOf(HeaderPageletLoadedHook::class, $this->controller->calledHook);
        static::assertSame($headerPagelet, $this->controller->calledHook->getPage());
    }

    public function testFooterRendersStorefront(): void
    {
        $request = new Request(['footerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateSalesChannelContext();
        $footerPagelet = new FooterPagelet(null, new CategoryCollection(), new PaymentMethodCollection(), new ShippingMethodCollection());

        $this->footerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($footerPagelet);

        $this->controller->footer($request, $context);
        static::assertSame('@Storefront/storefront/layout/footer.html.twig', $this->controller->renderStorefrontView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderStorefrontParameters['footerParameters']);

        static::assertInstanceOf(FooterPageletLoadedHook::class, $this->controller->calledHook);
        static::assertSame($footerPagelet, $this->controller->calledHook->getPage());
    }
}

/**
 * @internal
 */
class NavigationControllerTestClass extends NavigationController
{
    use StorefrontControllerMockTrait;
}
