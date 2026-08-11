<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Buckets a mail send into a small, bounded group, keyed by the triggering business event
 * (`MailService::send()`'s `$templateData['eventName']`, e.g. `checkout.order.placed`) rather than the mail
 * template: the template name is not available at the send site, and the event identifies the mail kind just
 * as well. Mails sent outside a flow (no event) resolve to `other`.
 *
 * Owns its bounded output set (closed maps, `other` as default), so the consuming metric label may use
 * `policy: open`. Known outputs: order_confirmation, order_state_change, delivery_state_change,
 * payment_state_change, customer_registration, customer_recovery, newsletter, contact_form, other.
 *
 * The hardcoded maps are intentional — see the rationale on
 * {@see \Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('after-sales')]
class MailGroupResolver
{
    /**
     * Exact event name → group, for events without a usable prefix pattern.
     *
     * @var array<string, string>
     */
    private const EVENTS = [
        'checkout.order.placed' => 'order_confirmation',
        'checkout.order.payment_method.changed' => 'payment_state_change',

        'checkout.customer.register' => 'customer_registration',
        'checkout.customer.guest_register' => 'customer_registration',
        'checkout.customer.double_opt_in_registration' => 'customer_registration',
        'checkout.customer.double_opt_in_guest_order' => 'customer_registration',
        'customer.group.registration.accepted' => 'customer_registration',
        'customer.group.registration.declined' => 'customer_registration',

        'customer.recovery.request' => 'customer_recovery',
        'user.recovery.request' => 'customer_recovery',

        'contact_form.send' => 'contact_form',
        'review_form.send' => 'contact_form',
    ];

    /**
     * State-machine → group for `state_{enter|leave}.{state_machine}.{state}` event names.
     *
     * @var array<string, string>
     */
    private const STATE_MACHINES = [
        'order' => 'order_state_change',
        'order_delivery' => 'delivery_state_change',
        'order_transaction' => 'payment_state_change',
        'order_transaction_capture_refund' => 'payment_state_change',
    ];

    public function resolve(?string $eventName): string
    {
        if ($eventName === null || $eventName === '') {
            return 'other';
        }

        return self::EVENTS[$eventName] ?? $this->resolveByPrefix($eventName);
    }

    private function resolveByPrefix(string $eventName): string
    {
        if (str_starts_with($eventName, 'state_enter.') || str_starts_with($eventName, 'state_leave.')) {
            return self::STATE_MACHINES[explode('.', $eventName)[1] ?? ''] ?? 'other';
        }

        if (str_starts_with($eventName, 'newsletter.')) {
            return 'newsletter';
        }

        return 'other';
    }
}
