<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the polymorphic `domain` label of a main HTTP request; the `area` label disambiguates the
 * overlapping values.
 *
 * By route shape:
 *  - frontend.* / store-api.*     → `route_domain` mapping (key: route name)
 *  - api.action.*                 → `action_domain` mapping (key: segment after `api.action.`); `api.action.sync` excluded
 *  - admin CRUD / clone / version → `entity_group` mapping via EntityGroupResolver (entity from the `entityName`/`entity` attribute)
 *  - anything else                → other
 *
 * Three mappings, one per key domain, so the label stays `policy: open` (see tables under `metrics.mappings`
 * in `telemetry.yaml`, extension and merge rules in `src/Core/Framework/Telemetry/README.md`).
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class DomainResolver
{
    final public const MAPPING_ROUTE_DOMAIN = 'route_domain';

    final public const MAPPING_ACTION_DOMAIN = 'action_domain';

    private const VERSION_SPECIALS = ['api.clone', 'api.createVersion', 'api.mergeVersion', 'api.deleteVersion'];

    public function __construct(
        private readonly LabelMappingRegistry $mappings,
        private readonly EntityGroupResolver $entityGroupResolver,
    ) {
    }

    public function resolve(Request $request): string
    {
        $route = (string) $request->attributes->get('_route', '');

        if (str_starts_with($route, 'frontend.') || str_starts_with($route, 'store-api.')) {
            return $this->mappings->resolve(self::MAPPING_ROUTE_DOMAIN, $route);
        }

        if ($route !== 'api.action.sync' && str_starts_with($route, 'api.action.')) {
            // strip 'api.action.' (11 chars), take the next dot-delimited segment
            $segment = explode('.', substr($route, 11))[0];

            return $this->mappings->resolve(self::MAPPING_ACTION_DOMAIN, $segment);
        }

        // admin CRUD: the entity is the `entityName` route default
        $entityName = $request->attributes->get('entityName');
        if (\is_string($entityName) && $entityName !== '') {
            return $this->entityGroup($entityName);
        }

        // clone / version: the entity is the `entity` route param
        if (\in_array($route, self::VERSION_SPECIALS, true)) {
            $entity = $request->attributes->get('entity');
            if (\is_string($entity) && $entity !== '') {
                return $this->entityGroup($entity);
            }
        }

        return 'other';
    }

    /**
     * Maps an admin API resource name to its entity group. Admin routes carry the hyphenated resource
     * name (e.g. `product-manufacturer`); entity groups key on the snake_case entity name.
     */
    private function entityGroup(string $resourceName): string
    {
        return $this->entityGroupResolver->resolve(str_replace('-', '_', $resourceName));
    }
}
