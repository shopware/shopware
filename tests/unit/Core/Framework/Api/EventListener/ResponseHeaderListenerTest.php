<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ResponseHeaderListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResponseHeaderListener::class)]
class ResponseHeaderListenerTest extends TestCase
{
    public function testCopiesVersionAndLanguageHeaders(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_VERSION_ID, 'version-id');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'language-id');

        $response = $this->handleResponse($request);

        static::assertSame('version-id', $response->headers->get(PlatformRequest::HEADER_VERSION_ID));
        static::assertSame('language-id', $response->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testDoesNotCopyContextTokenHeader(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'context-token');

        $response = $this->handleResponse($request);

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    #[DataProvider('contextTokenResponseRouteProvider')]
    public function testKeepsExplicitContextTokenHeaderForTokenResponseRoutes(string $route): void
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'request-context-token');
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'response-context-token');

        $response = $this->handleResponse($request, $response);

        static::assertSame('response-context-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    #[DataProvider('nonContextTokenResponseRouteProvider')]
    public function testRemovesExplicitContextTokenHeaderForOtherRoutes(?string $route): void
    {
        $request = new Request();
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }

        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'response-context-token');

        $response = $this->handleResponse($request, $response);

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function contextTokenResponseRouteProvider(): \Generator
    {
        yield 'imitate customer login' => ['store-api.account.imitate-customer-login'];
        yield 'login' => ['store-api.account.login'];
        yield 'logout' => ['store-api.account.logout'];
        yield 'register' => ['store-api.account.register'];
        yield 'register confirm' => ['store-api.account.register.confirm'];
        yield 'context gateway token command' => ['store-api.context.gateway'];
        yield 'guest order login' => ['store-api.order'];
    }

    /**
     * @return \Generator<string, array{string|null}>
     */
    public static function nonContextTokenResponseRouteProvider(): \Generator
    {
        yield 'missing route' => [null];
        yield 'context switch' => ['store-api.switch-context'];
        yield 'change language' => ['store-api.account.change-language'];
        yield 'change password' => ['store-api.account.change-password'];
        yield 'default billing address' => ['store-api.account.address.change.default.billing'];
        yield 'default shipping address' => ['store-api.account.address.change.default.shipping'];
        yield 'cart read' => ['store-api.checkout.cart.read'];
        yield 'admin proxy' => ['api.proxy.switch-customer'];
    }

    private function handleResponse(Request $request, ?Response $response = null): Response
    {
        $response ??= new Response();

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        (new ResponseHeaderListener())->onResponse($event);

        return $event->getResponse();
    }
}
