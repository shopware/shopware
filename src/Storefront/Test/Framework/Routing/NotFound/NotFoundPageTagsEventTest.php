<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing\NotFound;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageTagsEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Routing\NotFound\NotFoundPageTagsEvent
 */
class NotFoundPageTagsEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $event = new NotFoundPageTagsEvent(['test'], $request, $context);

        static::assertSame(['test'], $event->getTags());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame($context, $event->getSalesChannelContext());
        static::assertSame($request, $event->getRequest());

        $event->addTags(['test2']);
        static::assertSame(['test', 'test2'], $event->getTags());
    }
}
