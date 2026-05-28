<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacade;
use Shopware\Core\Framework\Script\Event\RenderStorefrontForScriptEvent;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
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

    #[TestDox('render() throws when called outside a SalesChannelContext')]
    public function testRenderThrowsWhenSalesChannelContextIsNull(): void
    {
        $facade = $this->buildFacade(salesChannelContext: null);

        $this->expectException(ScriptException::class);
        $this->expectExceptionMessageMatches('/sales.?channel/i');

        $facade->render('@Storefront/foo.html.twig');
    }

    #[TestDox('render() throws when no listener fills in the response (Storefront bundle missing)')]
    public function testRenderThrowsWhenNoListenerFillsResponse(): void
    {
        $facade = $this->buildFacade(
            eventDispatcher: new EventDispatcher(),
            salesChannelContext: static::createStub(SalesChannelContext::class)
        );

        $this->expectException(ScriptException::class);
        $this->expectExceptionMessageMatches('/storefront.*bundle/i');

        $facade->render('@Storefront/foo.html.twig');
    }

    #[TestDox('render() dispatches the event and returns the listener-provided response')]
    public function testRenderReturnsListenerProvidedResponse(): void
    {
        $rendered = new Response('rendered html', Response::HTTP_ACCEPTED);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            RenderStorefrontForScriptEvent::class,
            static function (RenderStorefrontForScriptEvent $event) use ($rendered): void {
                $event->response = $rendered;
            }
        );

        $facade = $this->buildFacade(
            eventDispatcher: $dispatcher,
            salesChannelContext: static::createStub(SalesChannelContext::class)
        );

        $response = $facade->render('@Storefront/foo.html.twig', ['page' => 'data']);

        static::assertSame($rendered, $response->getInner());
        static::assertSame(Response::HTTP_ACCEPTED, $response->getCode());
    }

    #[TestDox('render() forwards view name and parameters into the dispatched event payload')]
    public function testRenderForwardsViewAndParametersIntoEvent(): void
    {
        $captured = null;

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            RenderStorefrontForScriptEvent::class,
            static function (RenderStorefrontForScriptEvent $event) use (&$captured): void {
                $captured = $event;
                $event->response = new Response('ok');
            }
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $facade = $this->buildFacade(
            eventDispatcher: $dispatcher,
            salesChannelContext: $salesChannelContext
        );

        $facade->render('@Storefront/detail.html.twig', ['page' => 'data']);

        static::assertInstanceOf(RenderStorefrontForScriptEvent::class, $captured);
        static::assertSame('@Storefront/detail.html.twig', $captured->view);
        static::assertSame(['page' => 'data'], $captured->parameters);
        static::assertSame($salesChannelContext, $captured->salesChannelContext);
    }

    private function buildFacade(
        ?RouterInterface $router = null,
        ?EventDispatcher $eventDispatcher = null,
        ?SalesChannelContext $salesChannelContext = null,
    ): ScriptResponseFactoryFacade {
        return new ScriptResponseFactoryFacade(
            $router ?? static::createStub(RouterInterface::class),
            $eventDispatcher ?? new EventDispatcher(),
            $salesChannelContext,
        );
    }
}
