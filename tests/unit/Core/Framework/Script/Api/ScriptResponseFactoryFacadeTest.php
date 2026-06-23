<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptResponseFactoryFacade::class)]
class ScriptResponseFactoryFacadeTest extends TestCase
{
    #[TestDox('json() builds a ScriptResponse with the given body and status code')]
    public function testJsonReturnsScriptResponseWithBody(): void
    {
        $facade = $this->buildFacade();

        $response = $facade->json(['foo' => 'bar'], Response::HTTP_CREATED);

        static::assertSame(Response::HTTP_CREATED, $response->getCode());
        static::assertSame(['foo' => 'bar'], $response->getBody()->all());
    }

    #[TestDox('redirect() generates the URL via router and wraps it in a RedirectResponse')]
    public function testRedirectUsesRouterAndWrapsInRedirectResponse(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', ['foo' => 'bar'])
            ->willReturn('/home?foo=bar');

        $facade = $this->buildFacade($router);

        $response = $facade->redirect('frontend.home.page', ['foo' => 'bar']);

        $inner = $response->getInner();
        static::assertInstanceOf(RedirectResponse::class, $inner);
        static::assertSame('/home?foo=bar', $inner->getTargetUrl());
        static::assertSame(Response::HTTP_FOUND, $response->getCode());
    }

    #[TestDox('render() on the core facade is deprecated and throws because rendering needs the Storefront bundle')]
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRenderThrowsStorefrontBundleMissing(): void
    {
        $facade = $this->buildFacade(salesChannelContext: static::createStub(SalesChannelContext::class));

        $this->expectExceptionObject(
            ScriptException::storefrontBundleMissingForHookMethod(ScriptResponseFactoryFacade::class . '::render')
        );

        $facade->render('@Storefront/foo.html.twig');
    }

    private function buildFacade(
        ?RouterInterface $router = null,
        ?SalesChannelContext $salesChannelContext = null,
    ): ScriptResponseFactoryFacade {
        return new ScriptResponseFactoryFacade(
            $router ?? static::createStub(RouterInterface::class),
            $salesChannelContext,
        );
    }
}
