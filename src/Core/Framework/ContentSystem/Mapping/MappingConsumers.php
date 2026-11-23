<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Tells a data mapping (a consumer with a typed source) apart from ordinary context wiring.
 *
 * A mapping's consumer-map key names its destination. Ordinary wiring may carry a `propertyAlias`;
 * mappings must be optional, root-scoped, unaliased and non-redistributing at the decode boundary.
 * Validation, diagnostics, resolution, rendering and mutations share this predicate.
 * The Administration counterpart is `util/element-mapping.util.ts`.
 *
 * @internal
 */
#[Package('framework')]
final class MappingConsumers
{
    /**
     * @phpstan-assert-if-true MappingSourceReference $consumer->source
     *
     * @phpstan-assert-if-false null $consumer->source
     */
    public function isMapping(ContextConsumer $consumer): bool
    {
        return $consumer->source !== null;
    }

    /**
     * The mapped path behind each declared property this element fills by mapping rather than from its own
     * stored value.
     *
     * Keyed by property so a caller can test membership with `isset()` alone, and valued with the path
     * because the resolution layer reports it as the context key a mapped property resolves against.
     *
     * @return array<string, string> property key => mapped path, e.g. `['text' => 'category.name']`
     */
    public function mappedPaths(StoredElement $element): array
    {
        $paths = [];

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$this->isMapping($consumer)) {
                continue;
            }

            $paths[(string) $consumerKey] = $consumer->source->displayName();
        }

        return $paths;
    }
}
