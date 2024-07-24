<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheControlListener;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CacheControlListener::class)]
class CacheControlListenerTest extends TestCase
{
    #[DataProvider('headerCases')]
    public function testResponseHeaders(bool $reverseProxyEnabled, ?string $beforeHeader, string $afterHeader): void
    {
        $response = new Response();
        $response->headers->set(CacheResponseSubscriber::INVALIDATION_STATES_HEADER, 'foo');

        if ($beforeHeader) {
            $response->headers->set('cache-control', $beforeHeader);
        }

        $subscriber = new CacheControlListener($reverseProxyEnabled);

        $subscriber->__invoke(new BeforeSendResponseEvent(new Request(), $response));

        static::assertSame($afterHeader, $response->headers->get('cache-control'));

        if (!$reverseProxyEnabled) {
            static::assertFalse($response->headers->has(CacheResponseSubscriber::INVALIDATION_STATES_HEADER));
        }
    }

    /**
     * @return array<string, array<int, bool|string|null>>
     */
    public static function headerCases(): iterable
    {
        yield 'no cache proxy, default response' => [
            false,
            null,
            'no-cache, private',
        ];

        yield 'no cache proxy, default response with no-store (/account)' => [
            false,
            'no-store, private',
            'no-store, private',
        ];

        // @see: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control#preventing_storing
        yield 'no cache proxy, no-cache will be replaced with no-store' => [
            false,
            'no-store, no-cache, private',
            'no-store, private',
        ];

        yield 'no cache proxy, public content served as private for end client' => [
            false,
            'public, s-maxage=64000',
            'no-cache, private',
        ];

        yield 'cache proxy, cache-control is not touched' => [
            true,
            'public',
            'public',
        ];

        yield 'cache proxy, cache-control is not touched #2' => [
            true,
            'public, s-maxage=64000',
            'public, s-maxage=64000',
        ];

        yield 'cache proxy, cache-control is not touched #3' => [
            true,
            'private, no-store',
            'no-store, private', // Symfony sorts the cache-control
        ];
    }
}
