<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * The admissibility rules for stored data mappings, in one place and reporting-agnostic: every mapping names
 * a mappable property, a catalogued path, the projection the catalogue pairs that path with, and a value the
 * property can hold.
 *
 * It answers with {@see MappingProblem}s rather than with anything a caller can return directly, because it
 * has two callers that must not diverge. {@see StoredMappingValidator} refuses the `content_layout` write;
 * {@see LayoutDiagnostics} reports the same findings on the diagnose and draft mutation routes, which is
 * what the Experience Studio reads. Before that second surface existed, mapping an element to a path the
 * catalogue could already prove wrong was silent in the editor and only failed on save.
 *
 * The rules are keyed on the ROOT SOURCE, not on the root-ambient context: a catalogue lookup needs the
 * source id, and a resolved context cannot supply one. That is the reason this is not simply another check
 * inside the analysis, and the reason both callers have to be handed the source explicitly.
 *
 * WHAT COUNTS AS A MAPPING is the load-bearing decision here, and scope plus `propertyAlias` are not enough
 * to decide it. {@see ContextConsumerMirror} writes exactly that shape for a reference property it resolved
 * against the root-ambient set: `Sw:Product:Listing` receives the page's listing as a root-scoped consumer
 * keyed `productListing` aliased onto its declared `listing` property. That is wiring the mutation layer
 * proved, not something an author mapped, and demanding `mappable: true` for it rejects every listing page.
 *
 * That test lives in {@see MappingConsumers}, shared with the diagnostics layer so the two cannot drift.
 *
 * Two further shapes are deliberately left alone. A dotted root-scoped consumer whose alias does NOT name a
 * declared property stays unjudged: the content system has always let such a consumer deliver onto an
 * arbitrary undeclared key — {@see ContextDeliveryResolver::overlayRootContext()} writes the alias verbatim —
 * and that predates mapping. So does a parent-scoped consumer, which wires an ancestor rather than entity data.
 *
 * @internal
 */
#[Package('framework')]
final class StoredMappingInspector
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly AbstractContentSystemMappingCandidateRegistry $candidateRegistry,
        private readonly MappingTypeCompatibility $compatibility,
        private readonly MappingConsumers $mappingConsumers,
        private readonly AbstractContentSystemPropertyProjectionRegistry $projections,
    ) {
    }

    /**
     * @param list<StoredElement> $roots
     *
     * @return list<MappingProblem>
     */
    public function inspect(array $roots, string $rootSource): array
    {
        $problems = [];
        $candidates = $this->candidateRegistry->forRootSource($rootSource);

        foreach ($this->flatten($roots) as $element) {
            foreach ($this->inspectElement($element, $rootSource, $candidates) as $problem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /**
     * @param array<string, MappingCandidate> $candidates
     *
     * @return list<MappingProblem>
     */
    private function inspectElement(StoredElement $element, string $rootSource, array $candidates): array
    {
        if (!$this->typeRegistry->has($element->component)) {
            // An unregistered component is already an intrinsic well-formedness violation; this rule set does
            // not report it a second time under a mapping code.
            return [];
        }

        $declared = $this->typeRegistry->get($element->component)->properties();
        $problems = [];

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$this->mappingConsumers->isMapping($consumer, (string) $consumerKey)) {
                continue;
            }

            // Non-null by MappingConsumers::isMapping(), checked above.
            $propertyKey = (string) $consumer->propertyAlias;
            $property = $declared[$propertyKey] ?? null;

            if ($property === null) {
                continue;
            }

            $exception = $this->mappingFault((string) $consumerKey, $consumer, $property, $element->component, $rootSource, $candidates);

            if ($exception === null) {
                continue;
            }

            $problems[] = new MappingProblem($element->id, $propertyKey, (string) $consumerKey, $exception);
        }

        return $problems;
    }

    /**
     * @param array<string, MappingCandidate> $candidates
     */
    private function mappingFault(
        string $consumerKey,
        ContextConsumer $consumer,
        PropertySpecification $property,
        string $component,
        string $rootSource,
        array $candidates,
    ): ?ContentSystemException {
        // Non-null by MappingConsumers::isMapping(), checked at the call site.
        $propertyKey = (string) $consumer->propertyAlias;

        if (!$property->mappable()) {
            return ContentSystemException::propertyNotMappable($component, $propertyKey);
        }

        $candidate = $candidates[$consumerKey] ?? null;

        if ($candidate === null) {
            return ContentSystemException::unknownMappingPath($consumerKey, $rootSource);
        }

        // The candidate owns the pairing of path and projection, so the stored mapping has to carry the
        // projection the catalogue offered it with — including carrying none where the candidate has none.
        // Otherwise an author could keep a catalogued path and swap the transform, and the type check below
        // would then be checking a type nothing produces: valueType describes the path AFTER the candidate's
        // own projection, and says nothing about what a substituted one would yield.
        $projection = $consumer->projection;

        if ($projection !== $candidate->projection) {
            return ContentSystemException::mappingProjectionMismatch($consumerKey, $projection, $candidate->projection);
        }

        if ($projection !== null && $this->projections->get($projection) === null) {
            // The name came from the candidate, so a provider is offering a transform the container does not
            // have. Reported rather than thrown so one broken provider fails the layouts that use it instead
            // of every layout.
            return ContentSystemException::unknownPropertyProjection($projection, $consumerKey);
        }

        $declaredType = $property->type()->type();

        if (!$this->compatibility->permits($declaredType, $candidate->valueType)) {
            return ContentSystemException::mappingTypeMismatch(
                $propertyKey,
                \is_array($declaredType) ? implode('|', $declaredType) : $declaredType,
                $candidate->valueType,
            );
        }

        return null;
    }

    /**
     * @param list<StoredElement> $roots
     *
     * @return list<StoredElement>
     */
    private function flatten(array $roots): array
    {
        $flat = [];

        foreach ($roots as $root) {
            $flat[] = $root;

            foreach ($root->slots as $children) {
                foreach ($this->flatten($children) as $descendant) {
                    $flat[] = $descendant;
                }
            }
        }

        return $flat;
    }
}
