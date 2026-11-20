<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Buckets a mail send to the `mail_group` label value
 * (see `metrics.mappings.mail_group` in `telemetry.yaml`, extension and merge rules in `src/Core/Framework/Telemetry/README.md`).
 *
 * Key is a triggering business event (`MailService::send()`'s `$templateData['eventName']`, e.g.
 * `checkout.order.placed`) rather than the mail template: the template name is not available at the send
 * site, and the event can also identify the mail kind. Mails sent outside a flow (no event) resolve
 * to `other`.
 *
 * The `state_{enter|leave}.{state_machine}.{state}` family is parsed separately, `state_machine` is used as a result.
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
    final public const MAPPING = 'mail_group';

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

    public function __construct(private readonly LabelMappingRegistry $mappings)
    {
    }

    public function resolve(?string $eventName): string
    {
        return $this->mappings->resolve(self::MAPPING, $eventName ?? '', $this->stateMachineGroup(...));
    }

    private function stateMachineGroup(string $eventName): ?string
    {
        if (str_starts_with($eventName, 'state_enter.') || str_starts_with($eventName, 'state_leave.')) {
            return self::STATE_MACHINES[explode('.', $eventName)[1] ?? ''] ?? null;
        }

        return null;
    }
}
