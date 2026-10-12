<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\SearchController;
use Shopware\Storefront\Event\StorefrontRedirectEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoadedHook;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SearchController::class)]
class SearchControllerTest extends TestCase
{
    private SearchPageLoader&Stub $searchPageLoader;

    private SuggestPageLoader&Stub $suggestPageLoader;

    private AbstractProductSearchRoute&Stub $productSearchRoute;

    private SearchController $searchController;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->searchPageLoader = static::createStub(SearchPageLoader::class);
        $this->suggestPageLoader = static::createStub(SuggestPageLoader::class);
        $this->productSearchRoute = static::createStub(AbstractProductSearchRoute::class);

        $this->container = new ContainerBuilder();
        $this->container->set(SystemConfigService::class, static::createStub(SystemConfigService::class));
        $this->container->set(SeoUrlPlaceholderHandlerInterface::class, static::createStub(SeoUrlPlaceholderHandlerInterface::class));
        $this->container->set(MediaUrlPlaceholderHandlerInterface::class, static::createStub(MediaUrlPlaceholderHandlerInterface::class));
        $this->container->set('event_dispatcher', new EventDispatcher());
        $this->container->set(RequestTransformerInterface::class, static::createStub(RequestTransformerInterface::class));
        $this->container->set('http_kernel', static::createStub(HttpKernelInterface::class));
        $this->container->set('router', static::createStub(RouterInterface::class));
        $this->container->set('twig', static::createStub(Environment::class));

        $this->searchController = $this->createSearchController();
    }

    public function testSearchWithManyProductsFound(): void
    {
        $context = static::createStub(SalesChannelContext::class);

        $product1 = new ProductEntity();
        $product1->setProductNumber('test_1');
        $product1->setId('1');

        $product2 = new ProductEntity();
        $product2->setProductNumber('test_2');
        $product2->setId('2');

        $searchPage = new SearchPage();

        $searchPage->setListing(new ProductListingResult(
            ProductDefinition::ENTITY_NAME,
            1,
            new ProductCollection([$product1, $product2]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $hook = new SearchPageLoadedHook($searchPage, $context);

        $executor = static::createMock(ScriptExecutor::class);
        $executor
            ->expects($this->once())
            ->method('execute')
            ->with($hook);

        $request = new Request(
            query: ['search' => 'test'],
            attributes: [
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
                RequestTransformer::STOREFRONT_URL => 'http://localhost/search?search=test',
            ],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set(ScriptExecutor::class, $executor);
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('@Storefront/storefront/page/search/index.html.twig')
            ->willReturn('@Storefront/storefront/page/search/index.html.twig');

        $this->container->set(TemplateFinder::class, $templateFinder);

        $parameters = [
            'page' => $searchPage,
            'context' => $context,
            'headerParameters' => [],
            'footerParameters' => [],
        ];

        $twig = static::createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Storefront/storefront/page/search/index.html.twig', $parameters)
            ->willReturn('foo');

        $this->container->set('twig', $twig);

        $this->container->set('router', static::createStub(RouterInterface::class));

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())->method('load')->willReturn($searchPage);
        $searchController = $this->createSearchController($searchPageLoader);

        $response = $searchController->search($context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchWithNoProductsFound(): void
    {
        $context = static::createStub(SalesChannelContext::class);

        $searchPage = new SearchPage();
        $searchPage->setListing(new ProductListingResult(
            ProductDefinition::ENTITY_NAME,
            1,
            new ProductCollection([]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $hook = new SearchPageLoadedHook($searchPage, $context);

        $executor = static::createMock(ScriptExecutor::class);
        $executor
            ->expects($this->once())
            ->method('execute')
            ->with($hook);

        $request = new Request(
            query: ['search' => 'test'],
            attributes: [
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
                RequestTransformer::STOREFRONT_URL => 'http://localhost/search?search=test',
            ],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set(ScriptExecutor::class, $executor);
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('@Storefront/storefront/page/search/index.html.twig')
            ->willReturn('@Storefront/storefront/page/search/index.html.twig');

        $this->container->set(TemplateFinder::class, $templateFinder);

        $parameters = [
            'page' => $searchPage,
            'context' => $context,
            'headerParameters' => [],
            'footerParameters' => [],
        ];

        $twig = static::createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Storefront/storefront/page/search/index.html.twig', $parameters)
            ->willReturn('foo');

        $this->container->set('twig', $twig);

        $this->container->set('router', static::createStub(RouterInterface::class));

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())->method('load')->willReturn($searchPage);
        $searchController = $this->createSearchController($searchPageLoader);

        $response = $searchController->search($context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchWithoutSearchParameterShouldRedirectToHomePage(): void
    {
        $context = static::createStub(SalesChannelContext::class);

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())
            ->method('load')
            ->willThrowException(RoutingException::missingRequestParameter('search'));
        $searchController = $this->createSearchController($searchPageLoader);

        $request = new Request(
            query: ['search' => 'test'],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);

        $twig = static::createStub(Environment::class);
        $this->container->set('twig', $twig);

        $router = static::createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', [], 10)
            ->willReturn('http://localhost/');

        $router->expects($this->once())
            ->method('match')
            ->willReturn(['_controller' => SearchController::class]);

        $requestContext = new RequestContext();
        $router->method('getContext')
            ->willReturn($requestContext);

        $this->container->set('router', $router);

        $searchController->search($context, $request);
    }

    public function testSearchError(): void
    {
        $exception = RoutingException::invalidRequestParameter('test');

        $context = static::createStub(SalesChannelContext::class);

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())
            ->method('load')
            ->willThrowException($exception);
        $searchController = $this->createSearchController($searchPageLoader);

        static::expectExceptionObject($exception);
        $searchController->search($context, new Request());
    }

    /**
     * @return iterable<string, array{0: callable(ProductEntity): void, 1: string}>
     */
    public static function firstHitIdentifierProvider(): iterable
    {
        yield 'productNumber' => [
            static fn (ProductEntity $p) => $p->setProductNumber('SW-100'),
            'sw-100',
        ];

        yield 'productNumber matches search term in original casing' => [
            static fn (ProductEntity $p) => $p->setProductNumber('SW-100'),
            'SW-100',
        ];

        yield 'productNumber matches search term with surrounding whitespace' => [
            static fn (ProductEntity $p) => $p->setProductNumber('SW-100'),
            '  SW-100  ',
        ];

        yield 'ean' => [
            static fn (ProductEntity $p) => $p->setEan('4006381333931'),
            '4006381333931',
        ];

        yield 'ean matches search term with alphabetic characters' => [
            static fn (ProductEntity $p) => $p->setEan('BC1010'),
            'bc1010',
        ];

        yield 'manufacturerNumber' => [
            static fn (ProductEntity $p) => $p->setManufacturerNumber('MPN-XYZ'),
            'mpn-xyz',
        ];

        yield 'manufacturerNumber matches search term in original casing' => [
            static fn (ProductEntity $p) => $p->setManufacturerNumber('MPN-XYZ'),
            'MPN-XYZ',
        ];
    }

    /**
     * @param callable(ProductEntity): void $configureProduct
     */
    #[DataProvider('firstHitIdentifierProvider')]
    public function testSearchHandleFirstHit(callable $configureProduct, string $searchTerm): void
    {
        $request = new Request();
        $request->query->set('search', $searchTerm);

        $context = static::createStub(SalesChannelContext::class);

        $product = new ProductEntity();
        $product->setProductNumber('different-number');
        $product->setId('123');
        $configureProduct($product);

        $searchPage = new SearchPage();
        $searchPage->setListing(new ProductListingResult(
            ProductDefinition::ENTITY_NAME,
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $dispatcher = new EventDispatcher();

        $redirectEvent = null;
        $dispatcher->addListener(StorefrontRedirectEvent::class, static function (StorefrontRedirectEvent $event) use (&$redirectEvent): void {
            $redirectEvent = $event;
        });

        $router = static::createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with(ProductPageSeoUrlRoute::ROUTE_NAME, ['productId' => '123'])
            ->willReturn('http://localhost/product/123');

        $requestContext = new RequestContext();
        $router->method('getContext')
            ->willReturn($requestContext);

        $this->container->set('router', $router);
        $this->container->set('event_dispatcher', $dispatcher);

        $this->searchPageLoader->method('load')->willReturn($searchPage);

        $response = $this->searchController->search($context, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(StorefrontRedirectEvent::class, $redirectEvent);
        static::assertSame(Response::HTTP_FOUND, $redirectEvent->getStatus());
        static::assertSame(ProductPageSeoUrlRoute::ROUTE_NAME, $redirectEvent->getRoute());
        static::assertSame([
            'productId' => '123',
        ], $redirectEvent->getParameters());
    }

    public function testSearchDoesNotRedirectWhenFieldExcludedByConfig(): void
    {
        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $controller = $this->createSearchController($searchPageLoader, ['productNumber']);

        $context = static::createStub(SalesChannelContext::class);

        $product = new ProductEntity();
        $product->setProductNumber('different-number');
        $product->setEan('4006381333931');
        $product->setId('123');

        $searchPage = new SearchPage();
        $searchPage->setListing(new ProductListingResult(
            ProductDefinition::ENTITY_NAME,
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $request = new Request(
            query: ['search' => '4006381333931'],
            attributes: [
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
                RequestTransformer::STOREFRONT_URL => 'http://localhost/search?search=4006381333931',
            ],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set(ScriptExecutor::class, static::createStub(ScriptExecutor::class));

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('@Storefront/storefront/page/search/index.html.twig')
            ->willReturn('@Storefront/storefront/page/search/index.html.twig');
        $this->container->set(TemplateFinder::class, $templateFinder);

        $twig = static::createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturn('rendered');
        $this->container->set('twig', $twig);

        $router = static::createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');
        $this->container->set('router', $router);

        $searchPageLoader->expects($this->once())->method('load')->willReturn($searchPage);

        $response = $controller->search($context, $request);

        static::assertNotInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @param list<string>|null $redirectOnSingleHitFields
     */
    private function createSearchController(?SearchPageLoader $searchPageLoader = null, ?array $redirectOnSingleHitFields = null): SearchController
    {
        $controller = $redirectOnSingleHitFields === null
            ? new SearchController(
                $searchPageLoader ?? $this->searchPageLoader,
                $this->suggestPageLoader,
                $this->productSearchRoute,
            )
            : new SearchController(
                $searchPageLoader ?? $this->searchPageLoader,
                $this->suggestPageLoader,
                $this->productSearchRoute,
                $redirectOnSingleHitFields,
            );
        $controller->setContainer($this->container);

        return $controller;
    }
}
