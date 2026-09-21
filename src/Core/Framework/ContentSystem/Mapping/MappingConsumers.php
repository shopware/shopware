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
 * This lives in one place because two layers ask the question and must answer it identically:
 * `Validation/StoredMappingValidator` gates the write, and `Diagnostics/LayoutDiagnostics` has to know a
 * mapped property needs no loader input. A disagreement is silently wrong in both directions — a mapping the
 * gate skips goes unvalidated, and wiring diagnostics reads as a mapping stops demanding the input it needs.
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
     * The declared property keys this element fills by mapping rather than from its own stored value.
     *
     * @return array<string, true> keyed by property key, so a caller can test membership directly
     */
    public function mappedPropertyKeys(StoredElement $element): array
    {
        $keys = [];

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$this->isMapping($consumer, (string) $consumerKey)) {
                continue;
            }

            // Non-null by isMapping().
            $keys[(string) $consumer->propertyAlias] = true;
        }

        return $keys;
    }
}
