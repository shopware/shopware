<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * Operator policy for app telemetry contributions (`shopware.telemetry.metrics.accept_app_contributions`).
 *
 * The middle policy is a single per-entry predicate: an entry is accepted iff it does not introduce a
 * label value that the operator-declared config does not already contain. It keeps the safe contribution
 * class (grouping into the operator's taxonomy, zero cardinality impact) while rejecting value creation —
 * the knob a fleet operator needs when tenants can upload arbitrary apps.
 *
 * @internal
 */
#[Package('framework')]
enum AppContributionPolicy: string
{
    case ALL = 'all';
    case NO_NEW_VALUES = 'no-new-values';
    case NONE = 'none';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $policy) => $policy->value, self::cases());
    }
}
