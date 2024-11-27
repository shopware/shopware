<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $event = new SalesChannelContextCreatedEvent($salesChannelContext, $token);
        static::assertSame($token, $event->getUsedToken());
        static::assertSame($salesChannelContext, $event->getContext());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
    }
}
