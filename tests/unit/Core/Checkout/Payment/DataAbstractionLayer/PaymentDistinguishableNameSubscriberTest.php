<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentDistinguishableNameSubscriber::class)]
class PaymentDistinguishableNameSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                'payment_method.loaded' => 'addDistinguishablePaymentName',
            ],
            PaymentDistinguishableNameSubscriber::getSubscribedEvents()
        );
    }

    public function testAddName(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setName('test');
        $paymentMethod->addTranslated('name', 'translatedTest');

        $event = new EntityLoadedEvent(
            new PaymentMethodDefinition(),
            [$paymentMethod],
            Context::createDefaultContext()
        );

        $subscriber = new PaymentDistinguishableNameSubscriber();
        $subscriber->addDistinguishablePaymentName($event);

        static::assertSame('test', $paymentMethod->getDistinguishableName());
        static::assertSame('translatedTest', $paymentMethod->getTranslation('distinguishableName'));
    }
}
