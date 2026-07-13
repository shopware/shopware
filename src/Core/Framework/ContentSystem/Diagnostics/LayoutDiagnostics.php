<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * With a null root context only the intrinsic (well-formedness) subset runs; binding checks require a
 * root context.
 *
 * @final
 */
#[Package('framework')]
class LayoutDiagnostics
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AvailableContextResolver $availableContextResolver,
        private readonly ElementResolver $elementResolver,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly RootContextMapper $rootContextMapper,
        private readonly AbstractContentSystemDataLoaderMapResolver $mapResolver,
        private readonly DataLoaderConfigSerializerProvider $configSerializers,
    ) {
    }

    /**
     * @param list<ContentElement> $tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function analyze(array $tree, ?array $rootContext): LayoutAnalysis
    {
        $elements = $this->flatten($tree);

        $violations = [];
        $resolutions = [];

        foreach ($this->duplicateIdViolations($elements) as $violation) {
            $violations[] = $violation;
        }

        foreach ($elements as $element) {
            foreach ($this->intrinsicElementViolations($element) as $violation) {
                $violations[] = $violation;
            }

            $available = $this->availableContextResolver->resolve($element->getId(), $tree, $rootContext ?? []);
            $elementResolutions = $this->elementResolver->resolve($element, new ResolutionContext($element->getId(), $available));
            $resolutions[$element->getId()] = $elementResolutions;

            if ($rootContext === null) {
                continue;
            }

            foreach ($this->bindingViolations($element, $elementResolutions, $available) as $violation) {
                $violations[] = $violation;
            }
        }

        return new LayoutAnalysis(new DiagnosticsReport($violations), $resolutions);
    }

    /**
     * @param list<ContentElement> $elements
     *
     * @return list<Violation>
     */
    private function duplicateIdViolations(array $elements): array
    {
        $counts = [];
        foreach ($elements as $element) {
            $counts[$element->getId()] = ($counts[$element->getId()] ?? 0) + 1;
        }

        $violations = [];
        foreach ($counts as $id => $count) {
            if ($count < 2) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::DuplicateElementId,
                (string) $id,
                null,
                \sprintf('Element id "%s" is not unique across the layout.', $id),
            );
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function intrinsicElementViolations(ContentElement $element): array
    {
        $violations = [];

        if (!$this->registry->has($element->getComponent())) {
            $violations[] = new Violation(
                ViolationCode::UnregisteredComponent,
                $element->getId(),
                null,
                \sprintf('Component "%s" is not a registered element type.', $element->getComponent()),
            );
        }

        foreach ($element->getDataRequirements() as $key => $requirement) {
            $violation = $this->storedRequirementViolation($element, (string) $key, $requirement);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        foreach ($this->orphanedProviderViolations($element) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * One resolveType() call, one outcome: a config that fails to resolve (client defect) is InvalidConfig;
     * a config that resolves but produces a type not assignable to the property's declared reference FQCN
     * is MismatchedReferenceType; a config that resolves and fits yields no violation (the resolver reports
     * it as a Stored resolution instead).
     */
    private function storedRequirementViolation(ContentElement $element, string $key, DataRequirement $requirement): ?Violation
    {
        try {
            $produced = $this->rootContextMapper->resolveType($requirement);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return new Violation(ViolationCode::InvalidConfig, $element->getId(), $key, $exception->getMessage());
        }

        $declaredFqcn = $this->declaredReferenceFqcn($element->getComponent(), $key);

        if ($declaredFqcn === null || is_a($produced, $declaredFqcn, true)) {
            return null;
        }

        return new Violation(
            ViolationCode::MismatchedReferenceType,
            $element->getId(),
            $key,
            \sprintf('Stored wiring for "%s" produces "%s", which is not assignable to declared type "%s".', $key, $produced, $declaredFqcn),
        );
    }

    /**
     * The declared reference FQCN for a component's property, or null when the type is unregistered, the key
     * is not a declared property, or the property is not a single-FQCN reference (primitive or union type).
     */
    private function declaredReferenceFqcn(string $component, string $key): ?string
    {
        if (!$this->registry->has($component)) {
            return null;
        }

        $property = $this->registry->get($component)->properties()[$key] ?? null;

        if (!$property instanceof PropertySpecification) {
            return null;
        }

        $propertyType = $property->type();
        $declaredType = $propertyType->type();

        if ($propertyType->isPrimitive() || !\is_string($declaredType) || $declaredType === 'object') {
            return null;
        }

        return $declaredType;
    }

    /**
     * A provider whose key is consumed by no descendant element is orphaned (warning, non-blocking).
     *
     * @return list<Violation>
     */
    private function orphanedProviderViolations(ContentElement $element): array
    {
        $providers = $element->getProvidesContext();

        if ($providers === []) {
            return [];
        }

        $consumedKeys = [];
        foreach ($this->flatten([...$element->allSlotElements()]) as $descendant) {
            foreach ($descendant->getAcceptsContext() as $consumerKey => $consumer) {
                $consumedKeys[$consumerKey] = true;
            }
        }

        $violations = [];
        foreach ($providers as $providerKey => $provider) {
            if (isset($consumedKeys[$providerKey])) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::OrphanedProvider,
                $element->getId(),
                (string) $providerKey,
                \sprintf('Provider "%s" has no consumer in scope.', $providerKey),
            );
        }

        return $violations;
    }

    /**
     * @param list<PropertyResolution> $resolutions
     * @param list<ProvidedContext> $available
     *
     * @return list<Violation>
     */
    private function bindingViolations(ContentElement $element, array $resolutions, array $available): array
    {
        $violations = [];

        foreach ($resolutions as $resolution) {
            $violation = $this->propertyBindingViolation($element, $resolution);

            if ($violation !== null) {
                $violations[] = $violation;
            }

            foreach ($this->unfilledRequiredInputViolations($element, $resolution) as $unfilled) {
                $violations[] = $unfilled;
            }
        }

        return [...$violations, ...$this->brokenChainViolations($element, $available)];
    }

    private function propertyBindingViolation(ContentElement $element, PropertyResolution $resolution): ?Violation
    {
        if ($resolution->kind === PropertyKind::Primitive) {
            // Satisfied iff a value is stored on the element: serving applies no type default, so only a stored
            // value renders. The type default is a creation-time seed (scaffold + the write-boundary seeder),
            // not a render-time fallback, and so is not consulted here. A stored explicit null counts as no value
            // (it renders empty), so a required primitive authored as null is reported unresolved.
            if ($resolution->required && $element->getProperty($resolution->key) === null) {
                return new Violation(
                    ViolationCode::UnresolvedRequired,
                    $element->getId(),
                    $resolution->key,
                    \sprintf('Required property "%s" has no value.', $resolution->key),
                );
            }

            return null;
        }

        if ($resolution->resolved !== null) {
            return null;
        }

        if ($resolution->required) {
            $code = $this->usableCandidateCount($resolution->candidates) >= 2 ? ViolationCode::AmbiguousRequired : ViolationCode::UnresolvedRequired;

            return new Violation(
                $code,
                $element->getId(),
                $resolution->key,
                \sprintf('Required property "%s" is not deterministically resolvable.', $resolution->key),
                $resolution->candidates,
            );
        }

        if ($resolution->candidates === []) {
            return new Violation(
                ViolationCode::UnresolvedOptional,
                $element->getId(),
                $resolution->key,
                \sprintf('Optional property "%s" has no source.', $resolution->key),
            );
        }

        return null;
    }

    /**
     * A required reference satisfied by its own stored wiring (a {@see CandidateOrigin::Stored} pick) is
     * resolvable, but the loader still needs a value for each element property its config references. Every
     * required propertyReference config key whose configured property holds no value would serve an empty
     * element; each is one unfilled required input. Only a Stored resolution reaches this rule: a reference
     * satisfied by parent context or picked from a loader candidate never does, so those never gate, and an
     * optional or defaulted reference never gates either.
     *
     * @return list<Violation>
     */
    private function unfilledRequiredInputViolations(ContentElement $element, PropertyResolution $resolution): array
    {
        if ($resolution->kind !== PropertyKind::Reference || !$resolution->required) {
            return [];
        }

        $resolved = $resolution->resolved;

        if ($resolved === null || $resolved->origin !== CandidateOrigin::Stored) {
            return [];
        }

        // A Stored resolution forms only when ElementResolver::storedCandidate() found a stored requirement for
        // this key and its registered loader resolved the produced type (Resolution/ElementResolver::resolveReference),
        // so the requirement is present and its loader, hence its config specification, is registered here. The
        // `?? null` keeps that invariant explicit and narrows the offset for static analysis.
        $requirement = $element->getDataRequirements()[$resolution->key] ?? null;

        if ($requirement === null) {
            return [];
        }

        $specification = $this->mapResolver->resolve()->configSpecificationFor($requirement->source);
        // encode() cannot throw a client-defect here: the requirement's config object exists only by having been
        // decoded through this same source's serializer, and DI registration is static, so the serializer is
        // registered and round-trips a decoded object. A genuine encode failure is an internal fault that must
        // surface, so there is no client-defect catch (it would be unreachable).
        $config = $this->configSerializers->encode($requirement->source, $requirement->config);

        $violations = [];

        foreach ($specification->keysOfKind(ConfigKeyKind::PropertyReference) as $configKey) {
            if (!$configKey->required) {
                continue;
            }

            $configured = $config[$configKey->name] ?? null;

            if (!\is_string($configured)) {
                continue;
            }

            $violation = $this->unfilledInputViolation($element, $resolution->key, $configured);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    /**
     * One unfilled required input. Keyed on the input property the Admin highlights when the configured name is
     * a value-bearing (declared primitive) property; otherwise (the stored wiring is never property-name
     * validated) keyed on the reference property that does exist, naming the empty storage key in the message.
     * A resolvedBy reference's storage key is undeclared by design, so an empty value there is the normal
     * pre-fill state before the value is set and saved; a typo'd key is indistinguishable and reads the same
     * way. A stored explicit null counts as no value, mirroring the strict primitive rule above.
     */
    private function unfilledInputViolation(ContentElement $element, string $referenceKey, string $configuredProperty): ?Violation
    {
        if ($element->getProperty($configuredProperty) !== null) {
            return null;
        }

        if ($this->isDeclaredPrimitiveProperty($element->getComponent(), $configuredProperty)) {
            return new Violation(
                ViolationCode::UnfilledRequiredInput,
                $element->getId(),
                $configuredProperty,
                \sprintf('Required property "%s" is wired from "%s", which has no value.', $referenceKey, $configuredProperty),
            );
        }

        return new Violation(
            ViolationCode::UnfilledRequiredInput,
            $element->getId(),
            $referenceKey,
            \sprintf('Required property "%s" is wired from "%s", which has no value.', $referenceKey, $configuredProperty),
        );
    }

    private function isDeclaredPrimitiveProperty(string $component, string $key): bool
    {
        if (!$this->registry->has($component)) {
            return false;
        }

        $property = $this->registry->get($component)->properties()[$key] ?? null;

        if (!$property instanceof PropertySpecification) {
            return false;
        }

        return $property->type()->isPrimitive();
    }

    /**
     * Candidates the resolver could actually select: a parent (received-context) provider, or a loader whose
     * config is complete. Incomplete loaders cannot be picked, so 0 parents + N incomplete loaders is
     * unresolved, not ambiguous.
     *
     * @param list<ResolutionCandidate> $candidates
     */
    private function usableCandidateCount(array $candidates): int
    {
        $usable = array_filter(
            $candidates,
            // Stored never reaches this filter: ElementResolver never adds a Stored candidate to
            // PropertyResolution::candidates (it is only ever the resolved pick). The arm exists solely to
            // keep this match exhaustive over the three-case CandidateOrigin enum.
            static fn (ResolutionCandidate $candidate): bool => match ($candidate->origin) {
                CandidateOrigin::Parent => true,
                CandidateOrigin::Loader => $candidate->configComplete,
                CandidateOrigin::Stored => false,
            },
        );

        return \count($usable);
    }

    /**
     * @param list<ProvidedContext> $available
     *
     * @return list<Violation>
     */
    private function brokenChainViolations(ContentElement $element, array $available): array
    {
        $availableKeys = [];
        foreach ($available as $provided) {
            $availableKeys[$provided->contextKey] = true;
        }

        $violations = [];
        foreach ($element->getAcceptsContext() as $consumerKey => $consumer) {
            if (!$consumer->required || isset($availableKeys[$consumerKey])) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::BrokenRequiredChain,
                $element->getId(),
                (string) $consumerKey,
                \sprintf('Required context "%s" is provided by no ancestor or bound source.', $consumerKey),
            );
        }

        return $violations;
    }

    /**
     * @param array<ContentElement> $tree
     *
     * @return list<ContentElement>
     */
    private function flatten(array $tree): array
    {
        $elements = [];

        foreach ($tree as $element) {
            $elements[] = $element;

            foreach ($this->flatten([...$element->allSlotElements()]) as $descendant) {
                $elements[] = $descendant;
            }
        }

        return $elements;
    }
}
