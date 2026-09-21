<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\Log\Package;

/**
 * Tells an author's data mapping apart from the root-scoped context wiring that predates it.
 *
 * The two shapes very nearly coincide on the wire: both are root-scoped `acceptsContext` entries carrying a
 * `propertyAlias`. {@see ContextConsumerMirror} writes exactly that for a reference property it resolved
 * against the root-ambient set — `Sw:Product:Listing` receives the page's listing as a root-scoped consumer
 * keyed `productListing` aliased onto its declared `listing` property.
 *
 * The dot separates them. A mapping reads a PATH INTO an ambient value (`category.name`), while mirrored
 * wiring keys off the ambient value itself, and an ambient key is a bare data-requirement name that never
 * contains one. {@see MappingCandidate} holds the other half of the invariant by refusing an undotted path,
 * so the catalogue can never offer one that would read as wiring here.
 *
 * This lives in one place because three layers ask the question and must answer it identically:
 * {@see StoredMappingInspector} decides admissibility for the write gate and the diagnose routes,
 * `Diagnostics/LayoutDiagnostics` has to know a mapped property needs no loader input, and
 * `Resolution/ElementResolver` reports a mapped property as filled from root context. A disagreement is
 * silently wrong in every direction — a mapping a reader skips goes unvalidated, and wiring a reader takes
 * for a mapping stops demanding the input it needs or claims to fill a property nothing fills.
 * The Administration keeps its own copy of the rule in `util/element-mapping.util.ts`.
 *
 * @internal
 */
#[Package('framework')]
final class MappingConsumers
{
    public function isMapping(ContextConsumer $consumer, string $consumerKey): bool
    {
        return $consumer->scope === ConsumerScope::Root
            && $consumer->propertyAlias !== null
            && str_contains($consumerKey, '.');
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
            if (!$this->isMapping($consumer, (string) $consumerKey)) {
                continue;
            }

            // Non-null by isMapping().
            $paths[(string) $consumer->propertyAlias] = (string) $consumerKey;
        }

        return $paths;
    }
}
