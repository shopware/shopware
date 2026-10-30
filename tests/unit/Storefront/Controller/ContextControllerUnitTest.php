<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ContextController;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextController::class)]
class ContextControllerUnitTest extends TestCase
{
    public function testSwitchLangNoArgument(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $this->expectExceptionObject(RoutingException::missingRequestParameter('languageId'));

        $controller->switchLanguage(new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testSwitchLangNoString(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));

        $controller->switchLanguage(
            new Request([], ['languageId' => 1]),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testSwitchLangNoValidUuid(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));

        $controller->switchLanguage(
            new Request([], ['languageId' => 'noUuid']),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testSwitchLangNotFound(): void
    {
        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );
        $controller = new ContextController(
            $contextSwitchRoute,
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $notExistingLang = Uuid::randomHex();

        $this->expectExceptionObject(RoutingException::languageNotFound($notExistingLang));

        $controller->switchLanguage(
            new Request([], ['languageId' => $notExistingLang]),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testSwitchCustomerChange(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->once())->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $contextMock = static::createStub(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => null]),
            $contextMock
        );
    }

    public function testSwitchRedirectToNotExistingTarget(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $notExistingRedirectTo = 'frontend.homer.page';

        $contextMock = static::createStub(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => $notExistingRedirectTo]),
            $contextMock
        );
    }

    public function testSwitchRedirectToExistingTarget(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
        );

        $existingRedirectTo = 'frontend.home.page';

        $contextMock = static::createStub(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => $existingRedirectTo]),
            $contextMock
        );
    }

    public function testSwitchRedirectsToSeoUrlOfNewLanguageWithSchemeOfCurrentRequest(): void
    {
        $productId = Uuid::randomHex();
        $newLanguageId = Uuid::randomHex();

        $routerMock = $this->createRouterMock('/detail/' . $productId);

        $seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler->expects($this->exactly(2))
            ->method('generate')
            ->with(ProductPageSeoUrlRoute::ROUTE_NAME, ['productId' => $productId])
            ->willReturn('placeholder/detail/' . $productId . '#');

        $newContext = $this->createSalesChannelContext($newLanguageId);
        $seoUrlPlaceholderHandler->expects($this->once())
            ->method('replace')
            ->with('placeholder/detail/' . $productId . '#', 'https://localhost/en', $newContext)
            ->willReturn('http://localhost/en/test-product/SW10003');

        $controller = new ContextController(
            $this->createContextSwitchRoute('http://localhost/en/'),
            static::createStub(RequestStack::class),
            $routerMock,
            $seoUrlPlaceholderHandler,
        );

        $request = new Request([], [
            'languageId' => $newLanguageId,
            'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'redirectParameters' => json_encode(['productId' => $productId], \JSON_THROW_ON_ERROR),
        ]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $newContext);

        $response = $controller->switchLanguage($request, static::createStub(SalesChannelContext::class));

        static::assertSame('http://localhost/en/test-product/SW10003', $response->getTargetUrl());
    }

    #[DataProvider('seoUrlWithQueryParametersProvider')]
    public function testSwitchRedirectsToSeoUrlOfNewLanguageAndKeepsQueryParameters(string $seoUrl, string $expectedUrl): void
    {
        $productId = Uuid::randomHex();
        $newLanguageId = Uuid::randomHex();

        $routerMock = $this->createRouterMock('/detail/' . $productId . '?offcanvas=menu');

        $seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(static fn (string $name, array $parameters): string => 'placeholder/detail/' . $parameters['productId']
                . (isset($parameters['offcanvas']) ? '?offcanvas=' . $parameters['offcanvas'] : '') . '#');

        $newContext = $this->createSalesChannelContext($newLanguageId);
        $seoUrlPlaceholderHandler->expects($this->once())
            ->method('replace')
            ->with('placeholder/detail/' . $productId . '#', 'https://localhost/en', $newContext)
            ->willReturn($seoUrl);

        $controller = new ContextController(
            $this->createContextSwitchRoute('http://localhost/en'),
            static::createStub(RequestStack::class),
            $routerMock,
            $seoUrlPlaceholderHandler,
        );

        $request = new Request([], [
            'languageId' => $newLanguageId,
            'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'redirectParameters' => json_encode(['productId' => $productId, 'offcanvas' => 'menu'], \JSON_THROW_ON_ERROR),
        ]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $newContext);

        $response = $controller->switchLanguage($request, static::createStub(SalesChannelContext::class));

        static::assertSame($expectedUrl, $response->getTargetUrl());
    }

    public static function seoUrlWithQueryParametersProvider(): \Generator
    {
        yield 'query is appended to the SEO URL' => [
            'http://localhost/en/test-product/SW10003',
            'http://localhost/en/test-product/SW10003?offcanvas=menu',
        ];

        yield 'query is merged into a custom SEO URL that already has a query' => [
            'http://localhost/en/test-product/SW10003?variant=red',
            'http://localhost/en/test-product/SW10003?variant=red&offcanvas=menu',
        ];
    }

    public function testSwitchFallsBackToTechnicalUrlWithoutContextOfNewLanguage(): void
    {
        $productId = Uuid::randomHex();

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->method('generate')->willReturn('http://localhost/en/detail/' . $productId);

        $seoUrlPlaceholderHandler = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlPlaceholderHandler->expects($this->never())->method('replace');

        $controller = new ContextController(
            $this->createContextSwitchRoute('http://localhost/en'),
            static::createStub(RequestStack::class),
            $routerMock,
            $seoUrlPlaceholderHandler,
        );

        $request = new Request([], [
            'languageId' => Uuid::randomHex(),
            'redirectTo' => ProductPageSeoUrlRoute::ROUTE_NAME,
            'redirectParameters' => json_encode(['productId' => $productId], \JSON_THROW_ON_ERROR),
        ]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createSalesChannelContext(Uuid::randomHex()));

        $response = $controller->switchLanguage($request, static::createStub(SalesChannelContext::class));

        static::assertSame('http://localhost/en/detail/' . $productId, $response->getTargetUrl());
    }

    private function createRouterMock(string $path): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('getContext')->willReturn(new RequestContext(scheme: 'https'));
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = [], int $referenceType = RouterInterface::ABSOLUTE_PATH): string => $referenceType === RouterInterface::ABSOLUTE_URL
                ? 'https://localhost/en/'
                : $path
        );

        return $router;
    }

    private function createContextSwitchRoute(string $redirectUrl): ContextSwitchRoute
    {
        $contextSwitchRoute = static::createStub(ContextSwitchRoute::class);
        $contextSwitchRoute->method('switchContext')->willReturn(new ContextTokenResponse(Uuid::randomHex(), $redirectUrl));

        return $contextSwitchRoute;
    }

    private function createSalesChannelContext(string $languageId): SalesChannelContext
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn($languageId);

        return $context;
    }
}
