<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\NotFound;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageCacheKeyEvent
 */
class NotFoundPageCacheKeyEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $event = new NotFoundPageCacheKeyEvent('test', $request, $context);

        static::assertSame('test', $event->getKey());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($request, $event->getRequest());

        $event->setKey('test2');
        static::assertSame('test2', $event->getKey());
    }
}
