<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Adapter\Cache\Http\AdministrationCacheControlListener;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdministrationCacheControlListener::class)]
class AdministrationCacheControlListenerTest extends TestCase
{
    #[DataProvider('shouldSkipCacheControlProvider')]
    public function testShouldSkipCacheControl(
        Request $request,
        Response $response,
        bool $expectedSkip
    ): void {
        $listener = new AdministrationCacheControlListener();

        $event = new BeforeCacheControlEvent($request, $response);

        $listener->__invoke($event);

        static::assertSame($expectedSkip, $event->shouldSkipCacheControl());
    }

    /**
     * @return iterable<string, array{request: Request, response: Response, expectedSkip: bool}>
     */
    public static function shouldSkipCacheControlProvider(): iterable
    {
        yield 'administration cache ID header' => [
            'request' => new Request(),
            'response' => new Response('', 200, [
                AdministrationController::CACHE_ID_HEADER => AdministrationController::CACHE_ID_ADMINISTRATION,
            ]),
            'expectedSkip' => true,
        ];

        yield 'administration route scope' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]]
            ),
            'response' => new Response(),
            'expectedSkip' => true,
        ];

        yield 'administration route name' => [
            'request' => new Request(
                attributes: ['_route' => 'administration.index']
            ),
            'response' => new Response(),
            'expectedSkip' => true,
        ];

        yield 'administration route name with prefix' => [
            'request' => new Request(
                attributes: ['_route' => 'administration.plugin.index']
            ),
            'response' => new Response(),
            'expectedSkip' => true,
        ];

        yield 'multiple administration markers present' => [
            'request' => new Request(
                attributes: [
                    PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID],
                    '_route' => 'administration.index',
                ]
            ),
            'response' => new Response('', 200, [
                AdministrationController::CACHE_ID_HEADER => AdministrationController::CACHE_ID_ADMINISTRATION,
            ]),
            'expectedSkip' => true,
        ];

        yield 'no administration markers' => [
            'request' => new Request(),
            'response' => new Response(),
            'expectedSkip' => false,
        ];

        yield 'non-administration route scope' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['some-other-scope']]
            ),
            'response' => new Response(),
            'expectedSkip' => false,
        ];

        yield 'non-administration route name' => [
            'request' => new Request(
                attributes: ['_route' => 'storefront.page']
            ),
            'response' => new Response(),
            'expectedSkip' => false,
        ];

        yield 'wrong cache ID header value' => [
            'request' => new Request(),
            'response' => new Response('', 200, [
                AdministrationController::CACHE_ID_HEADER => 'different-value',
            ]),
            'expectedSkip' => false,
        ];

        yield 'non-string route name' => [
            'request' => new Request(
                attributes: ['_route' => 123]
            ),
            'response' => new Response(),
            'expectedSkip' => false,
        ];

        yield 'empty route scope array' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => []]
            ),
            'response' => new Response(),
            'expectedSkip' => false,
        ];
    }
}
