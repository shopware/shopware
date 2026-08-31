<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Buckets a flow trigger event name ({@see \Shopware\Core\Content\Flow\Dispatching\StorableFlow::getName()},
 * e.g. `checkout.order.placed`, `state_enter.order_transaction.state.paid`) into a small, bounded group set,
 * so the open set of business/app event names does not blow up the metric label cardinality.
 *
 * Ordered prefix match, first hit wins; unmatched names (incl. plugin/app events) fall through to `other`.
 * Owns its bounded output set, so the consuming metric label may use `policy: open`. Known outputs:
 * order, customer, checkout, state-change, app, content, other.
 *
 * Prefix resolver → memoizes per event name (workers are long-lived, each distinct name resolves once
 * per process). The hardcoded map is intentional — see the rationale on
 * {@see \Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('after-sales')]
class TriggerGroupResolver
{
    /**
     * Ordered prefix → group; more specific prefixes first.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        'state_enter.' => 'state-change',
        'state_leave.' => 'state-change',
        'checkout.order.' => 'order',
        'checkout.customer.' => 'customer',
        'customer.' => 'customer',
        'checkout.' => 'checkout',
        'app.' => 'app',
        'newsletter.' => 'content',
        'contact_form.' => 'content',
        'review_form.' => 'content',
        'revocation_request.' => 'content',
    ];

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function resolve(string $eventName): string
    {
        return $this->cache[$eventName] ??= $this->resolveUncached($eventName);
    }

    private function resolveUncached(string $eventName): string
    {
        foreach (self::PREFIXES as $prefix => $group) {
            if (str_starts_with($eventName, $prefix)) {
                return $group;
            }
        }

        return 'other';
    }
}
