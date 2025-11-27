<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HttpCacheCookieEvent::class)]
class HttpCacheCookieEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new HttpCacheCookieEvent(
            new Request(),
            $this->createMock(SalesChannelContext::class),
            [
                'foo' => 'bar',
            ]
        );
        static::assertSame('bar', $event->get('foo'));

        $event->add('test', 'test');
        static::assertSame('test', $event->get('test'));

        static::assertSame([
            'foo' => 'bar',
            'test' => 'test',
        ], $event->getParts());

        $event->remove('foo');
        static::assertNull($event->get('foo'));
        static::assertSame([
            'test' => 'test',
        ], $event->getParts());

        static::assertSame('cf2f7bb725c46c276355ae235de7ad52', $event->getHash());

        $event->isCacheable = false;

        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $event->getHash());
    }
}
