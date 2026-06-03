<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Event\PromotionCodeRedeemedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionCodeRedeemedEvent::class)]
class PromotionCodeRedeemedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'promotionId' => ['type' => 'string'],
            'codeId' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'orderId' => ['type' => 'string'],
            'customerId' => ['type' => 'string'],
        ], PromotionCodeRedeemedEvent::getAvailableData()->toArray());
    }

    public function testEventCarriesRedemptionData(): void
    {
        $promotionId = Uuid::randomHex();
        $codeId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $customerId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new PromotionCodeRedeemedEvent($context, $promotionId, $codeId, 'SUMMER-1', $orderId, $customerId);

        static::assertSame(PromotionCodeRedeemedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($promotionId, $event->getPromotionId());
        static::assertSame($codeId, $event->getCodeId());
        static::assertSame('SUMMER-1', $event->getCode());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame($customerId, $event->getCustomerId());
        static::assertSame([
            'promotionId' => $promotionId,
            'codeId' => $codeId,
            'code' => 'SUMMER-1',
            'orderId' => $orderId,
            'customerId' => $customerId,
        ], $event->getValues());
    }

    public function testGuestRedemptionHasNoCustomerId(): void
    {
        $event = new PromotionCodeRedeemedEvent(
            Context::createDefaultContext(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            'SUMMER-1',
            Uuid::randomHex()
        );

        static::assertNull($event->getCustomerId());
        static::assertNull($event->getValues()['customerId']);
    }
}
