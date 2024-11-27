<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing\NotFound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NotFoundPageCacheKeyEvent::class)]
class NotFoundPageCacheKeyEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = Generator::createSalesChannelContext();

        $event = new NotFoundPageCacheKeyEvent('test', $request, $context);

        static::assertSame('test', $event->getKey());
        static::assertSame($context, $event->getContext());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($request, $event->getRequest());

        $event->setKey('test2');
        static::assertSame('test2', $event->getKey());
    }
}
