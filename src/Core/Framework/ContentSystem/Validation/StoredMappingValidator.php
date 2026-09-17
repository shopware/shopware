<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * The data-mapping half of the content_layout write gate: every stored mapping names a mappable property, a
 * catalogued path, and a value the property can actually hold.
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
 * The dot separates them. A mapping reads a PATH INTO an ambient value (`category.name`), while a mirrored
 * consumer keys off the ambient value itself, and an ambient key is a bare data-requirement name that never
 * contains a dot. {@see MappingCandidate} holds the other half of that invariant by refusing an undotted path.
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
            if (!$this->isMapping($consumer, (string) $consumerKey)) {
                continue;
            }

            $property = $declared[$consumer->propertyAlias] ?? null;

            if ($property === null) {
                continue;
            }

            $violation = $this->mappingViolation($element, $consumerKey, $consumer->propertyAlias, $property, $rootSource, $candidates);

            if ($violation !== null) {
                $violations->add($violation);
            }
        }
    }

    /**
     * A consumer this gate owns, as opposed to one the mutation layer mirrored. See the class docblock for why
     * the dot is the discriminator.
     */
    private function isMapping(ContextConsumer $consumer, string $consumerKey): bool
    {
        return $consumer->scope === ConsumerScope::Root
            && $consumer->propertyAlias !== null
            && str_contains($consumerKey, '.');
    }

    /**
     * @param array<string, MappingCandidate> $candidates
     */
    private function mappingViolation(
        StoredElement $element,
        string $consumerKey,
        string $propertyKey,
        PropertySpecification $property,
        string $rootSource,
        array $candidates,
    ): ?ConstraintViolation {
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
