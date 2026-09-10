<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Telemetry\MailGroupResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailGroupResolver::class)]
class MailGroupResolverTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testResolve(?string $eventName, string $expected): void
    {
        static::assertSame($expected, (new MailGroupResolver())->resolve($eventName));
    }

    public static function eventProvider(): \Generator
    {
        // no triggering event (mails sent outside a flow) resolve to other
        yield 'null maps to other' => [null, 'other'];
        yield 'empty string maps to other' => ['', 'other'];

        // exact event-name lookup
        yield 'order placed maps to order_confirmation' => ['checkout.order.placed', 'order_confirmation'];
        yield 'order payment method changed maps to payment_state_change' => ['checkout.order.payment_method.changed', 'payment_state_change'];
        yield 'customer register maps to customer_registration' => ['checkout.customer.register', 'customer_registration'];
        yield 'group registration accepted maps to customer_registration' => ['customer.group.registration.accepted', 'customer_registration'];
        yield 'customer recovery request maps to customer_recovery' => ['customer.recovery.request', 'customer_recovery'];
        yield 'user recovery request maps to customer_recovery' => ['user.recovery.request', 'customer_recovery'];
        yield 'contact form send maps to contact_form' => ['contact_form.send', 'contact_form'];
        yield 'review form send maps to contact_form' => ['review_form.send', 'contact_form'];

        // state-machine prefix resolution
        yield 'order state completed maps to order_state_change' => ['state_enter.order.state.completed', 'order_state_change'];
        yield 'order state open (leave) maps to order_state_change' => ['state_leave.order.state.open', 'order_state_change'];
        yield 'order_delivery state shipped maps to delivery_state_change' => ['state_enter.order_delivery.state.shipped', 'delivery_state_change'];
        yield 'order_transaction state paid maps to payment_state_change' => ['state_enter.order_transaction.state.paid', 'payment_state_change'];
        yield 'order_transaction_capture_refund state completed maps to payment_state_change' => ['state_enter.order_transaction_capture_refund.state.completed', 'payment_state_change'];
        yield 'unknown state machine maps to other' => ['state_enter.custom_machine.state.x', 'other'];

        // newsletter prefix resolution
        yield 'newsletter register maps to newsletter' => ['newsletter.register', 'newsletter'];

        // unlisted events fall through to other
        yield 'plugin event maps to other' => ['some.plugin.event', 'other'];
    }
}
