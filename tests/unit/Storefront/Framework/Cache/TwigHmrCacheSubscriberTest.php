<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Cache\TwigHmrCacheSubscriber;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TwigHmrCacheSubscriber::class)]
class TwigHmrCacheSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [HttpCacheKeyEvent::class => 'bypassCache'],
            TwigHmrCacheSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideRequests')]
    public function testBypassCache(bool $debug, array $headers, bool $expectedCacheable): void
    {
        $request = new Request();
        $request->headers->add($headers);
        $event = new HttpCacheKeyEvent($request);

        (new TwigHmrCacheSubscriber($debug))->bypassCache($event);

        static::assertSame($expectedCacheable, $event->isCacheable);
    }

    /**
     * @return iterable<string, array{bool, array<string, string>, bool}>
     */
    public static function provideRequests(): iterable
    {
        yield 'hot reload request in debug mode' => [true, ['sw-twig-hmr' => '1'], false];
        yield 'regular request in debug mode' => [true, [], true];
        yield 'other header value in debug mode' => [true, ['sw-twig-hmr' => '0'], true];
        yield 'hot reload request without debug mode' => [false, ['sw-twig-hmr' => '1'], true];
    }
}
