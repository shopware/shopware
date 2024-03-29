<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SalesChannelContextCreatedEvent::class)]
class SalesChannelContextCreatedEventTest extends TestCase
{
    public function testEventReturnsAllNeededData(): void
    {
        $token = 'foo';
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $event = new SalesChannelContextCreatedEvent($salesChannelContext, $token);
        static::assertSame($token, $event->getUsedToken());
        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
    }
}
