<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentHandlerIdentifierSubscriber;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

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
            $this->getPaymentMethod(AppPaymentHandler::class),
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

        static::assertContainsOnlyInstancesOf(PaymentMethodEntity::class, $methods);
        static::assertCount(1, $methods);

        static::assertSame('handler_shopware_apppaymenthandler', $methods[0]->getFormattedHandlerIdentifier());
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

        static::assertContainsOnlyInstancesOf(PaymentMethodEntity::class, $methods);
        static::assertCount(1, $methods);

        static::assertSame('foo', $methods[0]->getFormattedHandlerIdentifier());
    }

    private function getPaymentMethod(string $identifierClass): PaymentMethodEntity
    {
        $entity = new PaymentMethodEntity();
        $entity->assign([
            'id' => Uuid::randomHex(),
            'handlerIdentifier' => $identifierClass,
        ]);

        return $entity;
    }
}
