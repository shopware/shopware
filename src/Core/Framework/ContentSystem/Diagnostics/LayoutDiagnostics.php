<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformance;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
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
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutDiagnostics
{
    public function __construct(
        private readonly AvailableContextResolver $availableContextResolver,
        private readonly ElementResolver $elementResolver,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly RootContextMapper $rootContextMapper,
        private readonly AbstractContentSystemDataLoaderMapResolver $mapResolver,
        private readonly DataLoaderConfigSerializerProvider $configSerializers,
        private readonly AbstractContentSystemStyleOptionRegistry $styleOptionRegistry,
    ) {
    }

    /**
     * @param list<StoredElement> $tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function analyze(array $tree, ?array $rootContext): LayoutAnalysis
    {
        $elements = $this->flatten($tree);

        $violations = [];
        $resolutions = [];
        $seenCollisions = [];

        // Read once per analysis rather than per element: the strict view is the same one the write boundary's
        // constraint descriptor reads, so the two cannot disagree about which options exist.
        $styleOptions = $this->styleOptionRegistry->all();

        foreach ($this->duplicateIdViolations($elements) as $violation) {
            $violations[] = $violation;
        }

        foreach ($elements as $element) {
            foreach ($this->intrinsicElementViolations($element, $styleOptions) as $violation) {
                $violations[] = $violation;
            }

            try {
                $available = $this->availableContextResolver->resolve($element->id, $tree, $rootContext ?? []);
            } catch (ContentSystemException $exception) {
                if (!ContentSystemException::isClientDefect($exception)) {
                    throw $exception;
                }

                // The context walk's one client-defect code: two providers of one element delivering to
                // children under the same child-facing key. A colliding element resolves nothing, so it
                // gets the violation and no resolutions entry — and neither does any descendant of it,
                // whose available context is genuinely unresolvable while the ancestor collides.
                //
                // The owner comes off the exception rather than from $element, and no test can currently
                // tell the two apart: flatten() is pre-order and resolve() validates its target before any
                // ancestor, so the owner always raises its own collision first and that entry is the one
                // the dedup keeps. Swapping this for $element->id leaves the suite green. It is not
                // redundant — it is what keeps the stamp right if either of those two traversal properties
                // ever changes, and nothing else pins them. Under a post-order walk the descendant would
                // raise first and $element->id would name an innocent element on the one surviving entry.
                $ownerId = $exception->getParameter('elementId');
                $first = $exception->getParameter('first');
                $second = $exception->getParameter('second');

                if (!\is_string($ownerId) || !\is_string($first) || !\is_string($second)) {
                    // Every client defect the context walk raises is a provider-delivery collision, and
                    // every one of those carries the declaring element and both colliding provider keys.
                    // Anything else is an internal fault mistyped as a client defect: surface it rather
                    // than stamp a violation onto an element that may not be the one at fault.
                    throw $exception;
                }

                // The violation names the element that DECLARES the collision, not the element the loop is
                // on: resolve() re-validates the whole ancestor path per element, so an owner with d
                // descendants raises the same collision d + 1 times, and the loop element names an innocent
                // descendant on d of them. Stamping the owner alone would only make those d + 1 entries
                // identical, so the seen-set collapses exact repeats — same code, same owner, same pair of
                // colliding keys. Keying it on the owner and the pair rather than on the code alone is what
                // keeps two elements' collisions apart.
                $collisionKey = implode("\0", [ViolationCode::InvalidConfig->value, $ownerId, $first, $second]);

                if (isset($seenCollisions[$collisionKey])) {
                    continue;
                }

                $seenCollisions[$collisionKey] = true;

                $violations[] = new Violation(ViolationCode::InvalidConfig, $ownerId, null, $exception->getMessage());

                continue;
            }

            $elementResolutions = $this->elementResolver->resolve($element, new ResolutionContext($element->id, $available));
            $resolutions[$element->id] = $elementResolutions;

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
     * @param list<StoredElement> $elements
     *
     * @return list<Violation>
     */
    private function duplicateIdViolations(array $elements): array
    {
        $counts = [];
        foreach ($elements as $element) {
            $counts[$element->id] = ($counts[$element->id] ?? 0) + 1;
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
     * @param array<string, StyleOptionSpecification> $styleOptions
     *
     * @return list<Violation>
     */
    private function intrinsicElementViolations(StoredElement $element, array $styleOptions): array
    {
        $violations = [];

        if (!$this->registry->has($element->component)) {
            $violations[] = new Violation(
                ViolationCode::UnregisteredComponent,
                $element->id,
                null,
                \sprintf('Component "%s" is not a registered element type.', $element->component),
            );
        }

        foreach ($element->dataRequirements as $key => $requirement) {
            $violation = $this->storedRequirementViolation($element, (string) $key, $requirement);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        foreach ($this->mismatchedPropertyTypeViolations($element) as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->unknownStyleOptionViolations($element, $styleOptions) as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->orphanedProviderViolations($element) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * A stored property value that disagrees with the primitive type its component declares for that key,
     * reported per key so a client can name and correct the one that broke. It is the diagnosis counterpart of
     * the write-path {@see PropertyTypeConformance} rule and applies the same boundary: only a key declared with
     * one of {@see PropertyType::PRIMITIVE_TYPES}, or a union whose members are all primitive, is judged, and a
     * stored null is admissible under every one of them (whether a key may be null is the required-input rule's
     * business). Like {@see ViolationCode::UnknownStyleOption} it never fires on a DAL write: the constraint pass
     * refuses the tree inside `encode()`, before the gate that reaches this class.
     *
     * @return list<Violation>
     */
    private function mismatchedPropertyTypeViolations(StoredElement $element): array
    {
        if (!$this->registry->has($element->component)) {
            return [];
        }

        $declared = $this->registry->get($element->component)->properties();
        $violations = [];

        foreach ($element->properties() as $key => $value) {
            $specification = $declared[$key] ?? null;

            if ($specification === null || $value->isNull()) {
                continue;
            }

            $types = $this->enforceablePrimitiveTypes($specification->type());

            if ($types === null) {
                continue;
            }

            $raw = $value->jsonSerialize();

            if ($this->matchesAnyPrimitiveType($raw, $types)) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::MismatchedPropertyType,
                $element->id,
                (string) $key,
                \sprintf('Property "%s" is declared as "%s" but carries a value of type "%s".', $key, implode('|', $types), get_debug_type($raw)),
            );
        }

        return $violations;
    }

    /**
     * The primitive types a stored value must satisfy at least one of, or `null` when the declaration constrains
     * nothing: a bare `object` or an FQCN admits whatever the client authored, and so does a union carrying
     * either. A union's declared type is an array, for which {@see PropertyType::isPrimitive()} always answers
     * false, so the members are tested against {@see PropertyType::PRIMITIVE_TYPES} directly.
     *
     * @return list<string>|null
     */
    private function enforceablePrimitiveTypes(PropertyType $type): ?array
    {
        $declared = $type->type();

        if (\is_string($declared)) {
            return \in_array($declared, PropertyType::PRIMITIVE_TYPES, true) ? [$declared] : null;
        }

        if ($declared === []) {
            return null;
        }

        foreach ($declared as $member) {
            if (!\in_array($member, PropertyType::PRIMITIVE_TYPES, true)) {
                return null;
            }
        }

        return $declared;
    }

    /**
     * @param list<string> $types
     */
    private function matchesAnyPrimitiveType(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            $matches = match ($type) {
                'string' => \is_string($value),
                'integer' => \is_int($value),
                'number' => \is_int($value) || \is_float($value),
                'boolean' => \is_bool($value),
                default => false,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * A style option the registry does not know, reported per option so a client can name and clear the one
     * that broke. It mirrors the unregistered-component rule: the write rejects such an option and the read
     * keeps it verbatim so an old layout still renders, and this makes the rejection legible instead of opaque.
     *
     * @param array<string, StyleOptionSpecification> $styleOptions
     *
     * @return list<Violation>
     */
    private function unknownStyleOptionViolations(StoredElement $element, array $styleOptions): array
    {
        $violations = [];

        foreach (array_keys($element->style->toArray()) as $name) {
            if (\array_key_exists($name, $styleOptions)) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::UnknownStyleOption,
                $element->id,
                $name,
                \sprintf('Style option "%s" is not a registered style option.', $name),
            );
        }

        return $violations;
    }

    /**
     * One resolveType() call, one outcome: a config that fails to resolve (client defect) is InvalidConfig;
     * a config that resolves but produces a type not assignable to the property's declared reference FQCN
     * is MismatchedReferenceType; a config that resolves and fits yields no violation (the resolver reports
     * it as a Stored resolution instead).
     */
    private function storedRequirementViolation(StoredElement $element, string $key, DataRequirement $requirement): ?Violation
    {
        try {
            $produced = $this->rootContextMapper->resolveType($requirement);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return new Violation(ViolationCode::InvalidConfig, $element->id, $key, $exception->getMessage());
        }

        $declaredFqcn = $this->declaredReferenceFqcn($element->component, $key);

        if ($declaredFqcn === null || is_a($produced, $declaredFqcn, true)) {
            return null;
        }

        return new Violation(
            ViolationCode::MismatchedReferenceType,
            $element->id,
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
    private function orphanedProviderViolations(StoredElement $element): array
    {
        $providers = $element->contextDefinitions->getAllProviders();

        if ($providers === []) {
            return [];
        }

        $consumedKeys = [];
        foreach ($this->flatten($this->directChildren($element)) as $descendant) {
            foreach ($descendant->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
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
                $element->id,
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
    private function bindingViolations(StoredElement $element, array $resolutions, array $available): array
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

    private function propertyBindingViolation(StoredElement $element, PropertyResolution $resolution): ?Violation
    {
        if ($resolution->kind === PropertyKind::Primitive) {
            // Satisfied iff a value is stored on the element: serving applies no type default, so only a stored
            // value renders. The type default is a creation-time seed (scaffold + the write-boundary seeder),
            // not a render-time fallback, and so is not consulted here. A stored explicit null counts as no value
            // (it renders empty), so a required primitive authored as null is reported unresolved.
            if ($resolution->required && !$this->hasStoredValue($element, $resolution->key)) {
                return new Violation(
                    ViolationCode::UnresolvedRequired,
                    $element->id,
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
                $element->id,
                $resolution->key,
                \sprintf('Required property "%s" is not deterministically resolvable.', $resolution->key),
                $resolution->candidates,
            );
        }

        if ($resolution->candidates === []) {
            return new Violation(
                ViolationCode::UnresolvedOptional,
                $element->id,
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
    private function unfilledRequiredInputViolations(StoredElement $element, PropertyResolution $resolution): array
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
        $requirement = $element->dataRequirements[$resolution->key] ?? null;

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
    private function unfilledInputViolation(StoredElement $element, string $referenceKey, string $configuredProperty): ?Violation
    {
        if ($this->hasStoredValue($element, $configuredProperty)) {
            return null;
        }

        if ($this->isDeclaredPrimitiveProperty($element->component, $configuredProperty)) {
            return new Violation(
                ViolationCode::UnfilledRequiredInput,
                $element->id,
                $configuredProperty,
                \sprintf('Required property "%s" is wired from "%s", which has no value.', $referenceKey, $configuredProperty),
            );
        }

        return new Violation(
            ViolationCode::UnfilledRequiredInput,
            $element->id,
            $referenceKey,
            \sprintf('Required property "%s" is wired from "%s", which has no value.', $referenceKey, $configuredProperty),
        );
    }

    /**
     * The one statement of "the element holds a value for this key", called from both satisfaction rules above.
     * {@see StoredElement::property()} separates the two empty cases the older model conflated: `null` means the
     * key is absent, while an authored explicit null comes back as a present stored value answering true to
     * `isNull()`. Both are "no value" for satisfaction, so a value counts only when the key is present AND its
     * variant is not null — a single-term `property($key) === null` test would silently credit an authored null.
     */
    private function hasStoredValue(StoredElement $element, string $key): bool
    {
        $value = $element->property($key);

        return $value !== null && !$value->isNull();
    }

    /**
     * True only for a single-primitive declared type: a union answers false here even though the serving
     * side treats every non-reference declaration as authored ({@see RenderedElementFactory}). The one
     * consequence is keying — a union-typed configured input property takes the reference-property
     * fallback at the call site instead of being keyed on itself. Consolidating this predicate onto
     * {@see PropertyType} beside the conformance rules is planned post-merge work.
     */
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
            // keep this match exhaustive over the four-case CandidateOrigin enum.
            static fn (ResolutionCandidate $candidate): bool => match ($candidate->origin) {
                CandidateOrigin::Parent => true,
                CandidateOrigin::Root => true,
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
    private function brokenChainViolations(StoredElement $element, array $available): array
    {
        $availableKeys = [];
        foreach ($available as $provided) {
            $availableKeys[$provided->contextKey] = true;
        }

        $violations = [];
        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$consumer->required || isset($availableKeys[$consumerKey])) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::BrokenRequiredChain,
                $element->id,
                (string) $consumerKey,
                \sprintf('Required context "%s" is provided by no ancestor or bound source.', $consumerKey),
            );
        }

        return $violations;
    }

    /**
     * @param array<StoredElement> $tree
     *
     * @return list<StoredElement>
     */
    private function flatten(array $tree): array
    {
        $elements = [];

        foreach ($tree as $element) {
            $elements[] = $element;

            foreach ($this->flatten($this->directChildren($element)) as $descendant) {
                $elements[] = $descendant;
            }
        }

        return $elements;
    }

    /**
     * Every direct child across all slots, in slot order. The storage model keys its children by slot rather
     * than exposing a flat walk, so the flattening the checks share starts here.
     *
     * @return list<StoredElement>
     */
    private function directChildren(StoredElement $element): array
    {
        return array_merge([], ...array_values($element->slots));
    }
}
