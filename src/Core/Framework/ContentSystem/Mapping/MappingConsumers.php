<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\Log\Package;

/**
 * Tells an author's data mapping apart from the root-scoped context wiring that predates it.
 *
 * Ordinary root-scoped wiring can carry a `propertyAlias`. {@see ContextConsumerMirror} writes that for a
 * reference property it resolved against the root-ambient set — `Sw:Product:Listing` receives the page's listing as a root-scoped consumer
 * keyed `productListing` aliased onto its declared `listing` property.
 *
 * A mapping carries a typed source reference in {@see ContextConsumer::$source}; ordinary wiring never does.
 * Its consumer-map key names the destination property, so one path may feed several properties.
 *
 * Validation, diagnostics, resolution, rendering and mutations share this predicate so they agree on which
 * consumers are mappings. At the decode boundary, a mapping must be optional, root-scoped, unaliased and
 * non-redistributing.
 * The Administration keeps its own copy of the rule in `util/element-mapping.util.ts`.
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
