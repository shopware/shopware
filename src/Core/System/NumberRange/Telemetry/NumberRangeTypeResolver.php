<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Buckets a number-range type technical name into a small, bounded set of groups, so plugin-defined
 * types don't blow up the metric label cardinality.
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric label may use
 * `policy: open`. Known outputs: order, customer, product, document, other. The document types share one
 * group: each is its own low-volume `number_range_state` row.
 *
 * The hardcoded map is intentional — see the rationale on
 * {@see \Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class NumberRangeTypeResolver
{
    /**
     * Core type technical names (defined in the basic-data migration) → group.
     *
     * @var array<string, string>
     */
    private const TYPES = [
        'order' => 'order',
        'customer' => 'customer',
        'product' => 'product',
        'document_invoice' => 'document',
        'document_delivery_note' => 'document',
        'document_credit_note' => 'document',
        'document_storno' => 'document',
    ];

    public function resolve(?string $technicalName): string
    {
        if ($technicalName === null) {
            return 'other';
        }

        return self::TYPES[$technicalName] ?? 'other';
    }
}
