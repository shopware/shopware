<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
#[Package('buyers-experience')]
#[CoversClass(SearchController::class)]
class SearchControllerTest extends TestCase
{
    private SearchPageLoader&MockObject $searchPageLoader;

    private SuggestPageLoader&MockObject $suggestPageLoader;

    private AbstractProductSearchRoute&MockObject $productSearchRoute;

    private SearchController $searchController;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->searchPageLoader = $this->createMock(SearchPageLoader::class);
        $this->suggestPageLoader = $this->createMock(SuggestPageLoader::class);
        $this->productSearchRoute = $this->createMock(AbstractProductSearchRoute::class);

        $this->searchController = new SearchController(
            $this->searchPageLoader,
            $this->suggestPageLoader,
            $this->productSearchRoute
        );

        $this->container = new ContainerBuilder();
        $this->container->set(SystemConfigService::class, $this->createMock(SystemConfigService::class));
        $this->container->set(SeoUrlPlaceholderHandlerInterface::class, $this->createMock(SeoUrlPlaceholderHandlerInterface::class));
        $this->container->set(MediaUrlPlaceholderHandlerInterface::class, $this->createMock(MediaUrlPlaceholderHandlerInterface::class));
        $this->container->set('event_dispatcher', new EventDispatcher());
        $this->container->set(RequestTransformerInterface::class, $this->createMock(RequestTransformerInterface::class));
        $this->container->set('http_kernel', $this->createMock(HttpKernelInterface::class));
        $this->container->set('router', static::createMock(RouterInterface::class));

        $this->searchController->setTwig(static::createMock(Environment::class));
        $this->searchController->setContainer($this->container);
    }

    public function testSearchWithManyProductsFound(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

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
            ->expects(static::once())
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
            ->expects(static::once())
            ->method('find')
            ->with('@Storefront/storefront/page/search/index.html.twig')
            ->willReturn('@Storefront/storefront/page/search/index.html.twig');

        $this->container->set(TemplateFinder::class, $templateFinder);

        $parameters = [
            'page' => $searchPage,
            'context' => $context,
        ];

        $twig = static::createMock(Environment::class);
        $twig->expects(static::once())
            ->method('render')
            ->with('@Storefront/storefront/page/search/index.html.twig', $parameters)
            ->willReturn('foo');

        $this->searchController->setTwig($twig);

        $this->container->set('router', $this->createMock(RouterInterface::class));

        $this->searchPageLoader->expects(static::once())->method('load')->willReturn($searchPage);

        $response = $this->searchController->search($context, $request);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchWithNoProductsFound(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

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
            ->expects(static::once())
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
            ->expects(static::once())
            ->method('find')
            ->with('@Storefront/storefront/page/search/index.html.twig')
            ->willReturn('@Storefront/storefront/page/search/index.html.twig');

        $this->container->set(TemplateFinder::class, $templateFinder);

        $parameters = [
            'page' => $searchPage,
            'context' => $context,
        ];

        $twig = static::createMock(Environment::class);
        $twig->expects(static::once())
            ->method('render')
            ->with('@Storefront/storefront/page/search/index.html.twig', $parameters)
            ->willReturn('foo');

        $this->searchController->setTwig($twig);

        $this->container->set('router', $this->createMock(RouterInterface::class));

        $this->searchPageLoader->expects(static::once())->method('load')->willReturn($searchPage);

        $response = $this->searchController->search($context, $request);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchWithoutSearchParameterShouldRedirectToHomePage(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $this->searchPageLoader->expects(static::once())
            ->method('load')
            ->willThrowException(RoutingException::missingRequestParameter('search'));

        $request = new Request(
            query: ['search' => 'test'],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);

        $twig = static::createMock(Environment::class);
        $this->searchController->setTwig($twig);

        $router = static::createMock(RouterInterface::class);
        $router->expects(static::once())
            ->method('generate')
            ->with('frontend.home.page', [], 10)
            ->willReturn('http://localhost/');

        $router->expects(static::once())
            ->method('match')
            ->willReturn(['_controller' => SearchController::class]);

        $requestContext = new RequestContext();
        $router->method('getContext')
            ->willReturn($requestContext);

        $this->container->set('router', $router);

        $this->searchController->search($context, $request);
    }

    public function testSearchError(): void
    {
        $exception = RoutingException::invalidRequestParameter('test');


        $context = $this->createMock(SalesChannelContext::class);

        $this->searchPageLoader->expects(static::once())
            ->method('load')
            ->willThrowException($exception);

        static::expectExceptionObject($exception);
        $this->searchController->search($context, new Request());
    }

    public function testSearchHandleFirstHit(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $context = $this->createMock(SalesChannelContext::class);

        $product = new ProductEntity();
        $product->setProductNumber('test');
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

        $dispatcher = new EventDispatcher();

        $redirectEvent = null;
        $dispatcher->addListener(StorefrontRedirectEvent::class, function (StorefrontRedirectEvent $event) use (&$redirectEvent): void {
            $redirectEvent = $event;
        });

        $router = static::createMock(RouterInterface::class);
        $router
            ->expects(static::once())
            ->method('generate')
            ->with('frontend.detail.page', ['productId' => '123'])
            ->willReturn('http://localhost/product/123');

        $requestContext = new RequestContext();
        $router->method('getContext')
            ->willReturn($requestContext);

        $this->container->set('router', $router);
        $this->container->set('event_dispatcher', $dispatcher);

        $this->searchPageLoader->method('load')->willReturn($searchPage);

        $response = $this->searchController->search($context, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertEquals(302, $response->getStatusCode());
        static::assertInstanceOf(StorefrontRedirectEvent::class, $redirectEvent);
        static::assertEquals(Response::HTTP_FOUND, $redirectEvent->getStatus());
        static::assertEquals('frontend.detail.page', $redirectEvent->getRoute());
        static::assertEquals([
            'productId' => '123',
        ], $redirectEvent->getParameters());
    }
}
