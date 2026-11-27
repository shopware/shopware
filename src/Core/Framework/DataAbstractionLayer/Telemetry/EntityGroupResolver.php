<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolves a DAL entity name (snake_case) to the `entity_group` label value
 * (see `metrics.mappings.entity_group` in `telemetry.yaml`, extension and merge rules in
 * `src/Core/Framework/Telemetry/README.md`).
 *
 * Shared resolver — reused by the HTTP request (admin-CRUD `domain`) and DAL search collectors.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class EntityGroupResolver
{
    final public const MAPPING = 'entity_group';

    public function __construct(private readonly LabelMappingRegistry $mappings)
    {
    }

    public function resolve(string $entityName): string
    {
        return $this->mappings->resolve(self::MAPPING, $entityName);
    }
}
