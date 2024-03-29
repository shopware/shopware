<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RecurringPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\PaymentHandler\MultipleTestPaymentHandler;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentHandlerIdentifierSubscriber::class)]
class PaymentHandlerIdentifierSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                'payment_method.loaded' => 'formatHandlerIdentifier',
                'payment_method.partial_loaded' => 'formatHandlerIdentifier',
            ],
            PaymentHandlerIdentifierSubscriber::getSubscribedEvents()
        );
    }

    public function testFormatHandlerIdentifier(): void
    {
        $paymentMethods = [
            $this->getPaymentMethod(),
            $this->getPaymentMethod(AsynchronousPaymentHandlerInterface::class),
            $this->getPaymentMethod(RefundPaymentHandlerInterface::class),
            $this->getPaymentMethod(PreparedPaymentHandlerInterface::class),
            $this->getPaymentMethod(RecurringPaymentHandlerInterface::class),
        ];

        $event = new EntityLoadedEvent(
            new PaymentMethodDefinition(),
            $paymentMethods,
            Context::createDefaultContext()
        );

        $subscriber = new PaymentHandlerIdentifierSubscriber();
        $subscriber->formatHandlerIdentifier($event);

        /** @var array<PaymentMethodEntity> $methods */
        $methods = $event->getEntities();

        static::assertContainsOnly(PaymentMethodEntity::class, $methods);
        static::assertCount(5, $methods);

        static::assertSame('handler_shopware_synchronouspaymenthandlerinterface', $methods[0]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_asynchronouspaymenthandlerinterface', $methods[1]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_refundpaymenthandlerinterface', $methods[2]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_preparedpaymenthandlerinterface', $methods[3]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_recurringpaymenthandlerinterface', $methods[4]->getFormattedHandlerIdentifier());

        static::assertTrue($methods[0]->isSynchronous());
        static::assertFalse($methods[0]->isAsynchronous());
        static::assertFalse($methods[0]->isRefundable());
        static::assertFalse($methods[0]->isPrepared());
        static::assertFalse($methods[0]->isRecurring());

        static::assertFalse($methods[1]->isSynchronous());
        static::assertTrue($methods[1]->isAsynchronous());
        static::assertFalse($methods[1]->isRefundable());
        static::assertFalse($methods[1]->isPrepared());
        static::assertFalse($methods[1]->isRecurring());

        static::assertFalse($methods[2]->isSynchronous());
        static::assertFalse($methods[2]->isAsynchronous());
        static::assertTrue($methods[2]->isRefundable());
        static::assertFalse($methods[2]->isPrepared());
        static::assertFalse($methods[2]->isRecurring());

        static::assertFalse($methods[3]->isSynchronous());
        static::assertFalse($methods[3]->isAsynchronous());
        static::assertFalse($methods[3]->isRefundable());
        static::assertTrue($methods[3]->isPrepared());
        static::assertFalse($methods[3]->isRecurring());

        static::assertFalse($methods[4]->isSynchronous());
        static::assertFalse($methods[4]->isAsynchronous());
        static::assertFalse($methods[4]->isRefundable());
        static::assertFalse($methods[4]->isPrepared());
        static::assertTrue($methods[4]->isRecurring());
    }

    public function testMultipleHandlerInterface(): void
    {
        $paymentMethods = [
            $this->getPaymentMethod(MultipleTestPaymentHandler::class),
        ];

        $event = new EntityLoadedEvent(
            new PaymentMethodDefinition(),
            $paymentMethods,
            Context::createDefaultContext()
        );

        $subscriber = new PaymentHandlerIdentifierSubscriber();
        $subscriber->formatHandlerIdentifier($event);

        /** @var array<PaymentMethodEntity> $methods */
        $methods = $event->getEntities();

        static::assertContainsOnly(PaymentMethodEntity::class, $methods);
        static::assertCount(1, $methods);

        static::assertSame('handler_shopware_multipletestpaymenthandler', $methods[0]->getFormattedHandlerIdentifier());

        static::assertTrue($methods[0]->isSynchronous());
        static::assertFalse($methods[0]->isAsynchronous());
        static::assertFalse($methods[0]->isRefundable());
        static::assertTrue($methods[0]->isPrepared());
        static::assertFalse($methods[0]->isRecurring());
    }

    public function testNonNamespacedIdentifier(): void
    {
        $paymentMethods = [
            $this->getPaymentMethod('foo'),
        ];

        $event = new EntityLoadedEvent(
            new PaymentMethodDefinition(),
            $paymentMethods,
            Context::createDefaultContext()
        );

        $subscriber = new PaymentHandlerIdentifierSubscriber();
        $subscriber->formatHandlerIdentifier($event);

        /** @var array<PaymentMethodEntity> $methods */
        $methods = $event->getEntities();

        static::assertContainsOnly(PaymentMethodEntity::class, $methods);
        static::assertCount(1, $methods);

        static::assertSame('foo', $methods[0]->getFormattedHandlerIdentifier());

        static::assertFalse($methods[0]->isSynchronous());
        static::assertFalse($methods[0]->isAsynchronous());
        static::assertFalse($methods[0]->isRefundable());
        static::assertFalse($methods[0]->isPrepared());
        static::assertFalse($methods[0]->isRecurring());
    }

    public function testAppPaymentMethod(): void
    {
        $method1 = $this->getPaymentMethod();
        $method1->setAppPaymentMethod((new AppPaymentMethodEntity())->assign(['payUrl' => 'foo']));

        $method2 = $this->getPaymentMethod(AsynchronousPaymentHandlerInterface::class);
        $method2->setAppPaymentMethod((new AppPaymentMethodEntity())->assign(['payUrl' => 'foo', 'finalizeUrl' => 'bar']));

        $method3 = $this->getPaymentMethod(RefundPaymentHandlerInterface::class);
        $method3->setAppPaymentMethod((new AppPaymentMethodEntity())->assign(['refundUrl' => 'foo']));

        $method4 = $this->getPaymentMethod(PreparedPaymentHandlerInterface::class);
        $method4->setAppPaymentMethod((new AppPaymentMethodEntity())->assign(['validateUrl' => 'foo', 'captureUrl' => 'bar']));

        $method5 = $this->getPaymentMethod(RecurringPaymentHandlerInterface::class);
        $method5->setAppPaymentMethod((new AppPaymentMethodEntity())->assign(['recurringUrl' => 'foo']));

        $paymentMethods = [$method1, $method2, $method3, $method4, $method5];

        $event = new EntityLoadedEvent(
            new PaymentMethodDefinition(),
            $paymentMethods,
            Context::createDefaultContext()
        );

        $subscriber = new PaymentHandlerIdentifierSubscriber();
        $subscriber->formatHandlerIdentifier($event);

        /** @var array<PaymentMethodEntity> $methods */
        $methods = $event->getEntities();

        static::assertContainsOnly(PaymentMethodEntity::class, $methods);
        static::assertCount(5, $methods);

        static::assertSame('handler_shopware_synchronouspaymenthandlerinterface', $methods[0]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_asynchronouspaymenthandlerinterface', $methods[1]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_refundpaymenthandlerinterface', $methods[2]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_preparedpaymenthandlerinterface', $methods[3]->getFormattedHandlerIdentifier());
        static::assertSame('handler_shopware_recurringpaymenthandlerinterface', $methods[4]->getFormattedHandlerIdentifier());

        static::assertTrue($methods[0]->isSynchronous());
        static::assertFalse($methods[0]->isAsynchronous());
        static::assertFalse($methods[0]->isRefundable());
        static::assertFalse($methods[0]->isPrepared());
        static::assertFalse($methods[0]->isRecurring());

        static::assertFalse($methods[1]->isSynchronous());
        static::assertTrue($methods[1]->isAsynchronous());
        static::assertFalse($methods[1]->isRefundable());
        static::assertFalse($methods[1]->isPrepared());
        static::assertFalse($methods[1]->isRecurring());

        static::assertTrue($methods[2]->isSynchronous());
        static::assertFalse($methods[2]->isAsynchronous());
        static::assertTrue($methods[2]->isRefundable());
        static::assertFalse($methods[2]->isPrepared());
        static::assertFalse($methods[2]->isRecurring());

        static::assertTrue($methods[3]->isSynchronous());
        static::assertFalse($methods[3]->isAsynchronous());
        static::assertFalse($methods[3]->isRefundable());
        static::assertTrue($methods[3]->isPrepared());
        static::assertFalse($methods[3]->isRecurring());

        static::assertTrue($methods[4]->isSynchronous());
        static::assertFalse($methods[4]->isAsynchronous());
        static::assertFalse($methods[4]->isRefundable());
        static::assertFalse($methods[4]->isPrepared());
        static::assertTrue($methods[4]->isRecurring());
    }

    private function getPaymentMethod(string $identifierClass = SynchronousPaymentHandlerInterface::class): PaymentMethodEntity
    {
        $entity = new PaymentMethodEntity();
        $entity->assign([
            'id' => Uuid::randomHex(),
            'handlerIdentifier' => $identifierClass,
        ]);

        return $entity;
    }
}
