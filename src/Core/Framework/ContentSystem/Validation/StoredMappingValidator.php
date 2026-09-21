<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The data-mapping half of the content_layout write gate: every stored mapping names a mappable property, a
 * catalogued path, the projection the catalogue pairs that path with, and a value the property can hold.
 *
 * It lives at the write boundary rather than in `Diagnostics/LayoutDiagnostics` because the rules need the
 * layout's root source, and `LayoutDiagnostics::analyze()` is handed the resolved root CONTEXT instead — it
 * could not look a candidate up. Reporting these as diagnostics violations on the diagnose and draft mutation
 * routes is a separate step, and takes threading the root source through that layer.
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
final class StoredMappingValidator
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
     */
    public function validate(array $roots, string $rootSource): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        $candidates = $this->candidateRegistry->forRootSource($rootSource);

        foreach ($this->flatten($roots) as $element) {
            $this->validateElement($element, $rootSource, $candidates, $violations);
        }

        return $violations;
    }

    /**
     * @param array<string, MappingCandidate> $candidates
     */
    private function validateElement(
        StoredElement $element,
        string $rootSource,
        array $candidates,
        ConstraintViolationList $violations,
    ): void {
        if (!$this->typeRegistry->has($element->component)) {
            // An unregistered component is already an intrinsic well-formedness violation; this gate does not
            // report it a second time under a mapping code.
            return;
        }

        $declared = $this->typeRegistry->get($element->component)->properties();

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$this->mappingConsumers->isMapping($consumer, (string) $consumerKey)) {
                continue;
            }

            $property = $declared[$consumer->propertyAlias] ?? null;

            if ($property === null) {
                continue;
            }

            $violation = $this->mappingViolation($element, (string) $consumerKey, $consumer, $property, $rootSource, $candidates);

            if ($violation !== null) {
                $violations->add($violation);
            }
        }
    }

    /**
     * @param array<string, MappingCandidate> $candidates
     */
    private function mappingViolation(
        StoredElement $element,
        string $consumerKey,
        ContextConsumer $consumer,
        PropertySpecification $property,
        string $rootSource,
        array $candidates,
    ): ?ConstraintViolation {
        // Non-null by MappingConsumers::isMapping(), checked at the call site.
        $propertyKey = (string) $consumer->propertyAlias;

        if (!$property->mappable()) {
            return $this->toViolation(
                ContentSystemException::propertyNotMappable($element->component, $propertyKey),
                $element->id,
                $propertyKey,
                $consumerKey,
            );
        }

        $candidate = $candidates[$consumerKey] ?? null;

        if ($candidate === null) {
            return $this->toViolation(
                ContentSystemException::unknownMappingPath($consumerKey, $rootSource),
                $element->id,
                $propertyKey,
                $consumerKey,
            );
        }

        // The candidate owns the pairing of path and projection, so the stored mapping has to carry the
        // projection the catalogue offered it with — including carrying none where the candidate has none.
        // Otherwise an author could keep a catalogued path and swap the transform, and the type check below
        // would then be checking a type nothing produces: valueType describes the path AFTER the candidate's
        // own projection, and says nothing about what a substituted one would yield.
        $projection = $consumer->projection;

        if ($projection !== $candidate->projection) {
            return $this->toViolation(
                ContentSystemException::mappingProjectionMismatch($consumerKey, $projection, $candidate->projection),
                $element->id,
                $propertyKey,
                $consumerKey,
            );
        }

        if ($projection !== null && $this->projections->get($projection) === null) {
            // A 500: the name came from the candidate, so a provider is offering a transform the container
            // does not have. Reported as a violation rather than thrown so one broken provider fails the
            // writes that use it instead of every write of every layout.
            return $this->toViolation(
                ContentSystemException::unknownPropertyProjection($projection, $consumerKey),
                $element->id,
                $propertyKey,
                $consumerKey,
            );
        }

        $declaredType = $property->type()->type();

        if (!$this->compatibility->permits($declaredType, $candidate->valueType)) {
            return $this->toViolation(
                ContentSystemException::mappingTypeMismatch(
                    $propertyKey,
                    \is_array($declaredType) ? implode('|', $declaredType) : $declaredType,
                    $candidate->valueType,
                ),
                $element->id,
                $propertyKey,
                $consumerKey,
            );
        }

        return null;
    }

    /**
     * Keyed on the element and the mapped PROPERTY rather than on the consumer key, matching
     * {@see ViolationConstraintMapper}'s `/{elementId}/{key}` shape, so the Administration highlights the
     * control the author acted on rather than a wiring key it does not render.
     */
    private function toViolation(
        ContentSystemException $exception,
        string $elementId,
        string $propertyKey,
        string $invalidValue,
    ): ConstraintViolation {
        return new ConstraintViolation(
            $exception->getMessage(),
            $exception->getMessage(),
            [],
            null,
            \sprintf('/%s/%s', $elementId, $propertyKey),
            $invalidValue,
            null,
            $exception->getErrorCode(),
        );
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
