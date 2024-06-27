<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\PaymentHandler\MultipleTestPaymentHandler;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - will be removed with new payment handlers
 */
#[Package('checkout')]
#[CoversClass(PaymentHandlerIdentifierSubscriber::class)]
class PaymentMethodHandlerRuntimeFieldTest extends TestCase
{
    public function testSynchronousRuntimeField(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                $this->createMock(SynchronousPaymentHandlerInterface::class)::class
            ));

        (new PaymentHandlerIdentifierSubscriber())->formatHandlerIdentifier($event);

        static::assertCount(1, $event->getEntities());

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $event->getEntities()[0];

        static::assertTrue($paymentMethod->isSynchronous());
        static::assertFalse($paymentMethod->isAsynchronous());
        static::assertFalse($paymentMethod->isPrepared());
    }

    public function testAsynchronousRuntimeField(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                $this->createMock(AsynchronousPaymentHandlerInterface::class)::class
            ));

        (new PaymentHandlerIdentifierSubscriber())->formatHandlerIdentifier($event);

        static::assertCount(1, $event->getEntities());

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $event->getEntities()[0];

        static::assertFalse($paymentMethod->isSynchronous());
        static::assertTrue($paymentMethod->isAsynchronous());
        static::assertFalse($paymentMethod->isPrepared());
    }

    public function testPreparedRuntimeField(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                $this->createMock(PreparedPaymentHandlerInterface::class)::class
            ));

        (new PaymentHandlerIdentifierSubscriber())->formatHandlerIdentifier($event);

        static::assertCount(1, $event->getEntities());

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $event->getEntities()[0];

        static::assertFalse($paymentMethod->isSynchronous());
        static::assertFalse($paymentMethod->isAsynchronous());
        static::assertTrue($paymentMethod->isPrepared());
    }

    public function testMultipleRuntimeFieldsAtOnce(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                $this->createMock(MultipleTestPaymentHandler::class)::class
            ));

        (new PaymentHandlerIdentifierSubscriber())->formatHandlerIdentifier($event);

        static::assertCount(1, $event->getEntities());

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $event->getEntities()[0];

        static::assertTrue($paymentMethod->isSynchronous());
        static::assertFalse($paymentMethod->isAsynchronous());
        static::assertTrue($paymentMethod->isPrepared());
    }

    /**
     * @return PaymentMethodEntity[]
     */
    private function getPaymentMethodEntity(string $handlerIdentifier): array
    {
        return [(new PaymentMethodEntity())->assign(['handlerIdentifier' => $handlerIdentifier])];
    }
}
