<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\MultipleTestPaymentHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

/**
 * @package checkout
 *
 * @internal
 */
class PaymentMethodHandlerRuntimeFieldTest extends TestCase
{
    public function testSynchronousRuntimeField(): void
    {
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                \get_class($this->createMock(SynchronousPaymentHandlerInterface::class))
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
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                \get_class($this->createMock(AsynchronousPaymentHandlerInterface::class))
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
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                \get_class($this->createMock(PreparedPaymentHandlerInterface::class))
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
        $event = $this->createMock(EntityLoadedEvent::class);
        $event
            ->method('getEntities')
            ->willReturn($this->getPaymentMethodEntity(
                \get_class($this->createMock(MultipleTestPaymentHandler::class))
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
