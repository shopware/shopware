<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformance;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
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
use Shopware\Core\System\Language\LanguageLoaderInterface;

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
        private readonly ContextPathResolver $contextPathResolver,
        private readonly LanguageLoaderInterface $languageLoader,
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

        // Read at most once per analysis, and only when a tree carries a translatable property: the memo keeps
        // the per-analyze() freshness the loader read promises while a tree with no translatable property pays
        // no language read. The loader caches internally and invalidates on LANGUAGE_WRITTEN/LANGUAGE_DELETED,
        // so that invalidation defines freshness.
        $languageIdsMemo = null;
        $languageIds = function () use (&$languageIdsMemo): array {
            return $languageIdsMemo ??= $this->existingLanguageIds();
        };

        foreach ($this->duplicateIdViolations($elements) as $violation) {
            $violations[] = $violation;
        }

        foreach ($elements as $element) {
            foreach ($this->intrinsicElementViolations($element, $styleOptions, $languageIds) as $violation) {
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
     * The set of language ids that exist, backed by the platform's cached language loader. The loader keys its
     * result by language id in the same lowercase-hex shape a stored language map is keyed by, so an entry key
     * matches by string identity.
     *
     * Read lazily per `analyze()` and never cached on the instance: a long-lived instance caching the set would
     * report a freshly created language as dangling. Freshness itself follows the loader, which invalidates its
     * cache on LANGUAGE_WRITTEN_EVENT and LANGUAGE_DELETED_EVENT; a language row written without the DAL fires
     * no event and leaves the cached set stale until the next invalidation.
     *
     * @return array<string, true>
     */
    private function existingLanguageIds(): array
    {
        return array_fill_keys(array_keys($this->languageLoader->loadLanguages()), true);
    }

    /**
     * @param array<string, StyleOptionSpecification> $styleOptions
     * @param \Closure(): array<string, true> $languageIds
     *
     * @return list<Violation>
     */
    private function intrinsicElementViolations(StoredElement $element, array $styleOptions, \Closure $languageIds): array
    {
        $violations = [];

        // Derived once and shared by both property checks below, so they judge one declaration snapshot. A null
        // map is the unregistered case, which is the same condition the violation reports.
        $declared = $this->declaredProperties($element->component);

        if ($declared === null) {
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

        foreach ($this->mismatchedPropertyTypeViolations($element, $declared) as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->unknownStyleOptionViolations($element, $styleOptions) as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->orphanedProviderViolations($element) as $violation) {
            $violations[] = $violation;
        }

        foreach ($this->danglingLanguageViolations($element, $declared, $languageIds) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * A stored property value the type its component declares for that key does not admit, reported per key so
     * a client can name and correct the one that broke. It is the diagnosis counterpart of the write-path
     * {@see PropertyTypeConformance} rule and shares its one predicate, {@see PropertyType::admits()}: an
     * unconstraining declaration (a bare `object`, an FQCN, a union carrying either) admits whatever the client
     * authored, a non-translatable declaration admits the null variant, and a translatable declaration admits
     * only a non-empty language map. Like {@see ViolationCode::UnknownStyleOption} it never fires on a DAL
     * write: the constraint pass refuses the tree inside `encode()`, before the gate that reaches this class.
     *
     * @param array<string, PropertySpecification>|null $declared the component's declared properties, or null when unregistered
     *
     * @return list<Violation>
     */
    private function mismatchedPropertyTypeViolations(StoredElement $element, ?array $declared): array
    {
        if ($declared === null) {
            return [];
        }

        $violations = [];

        foreach ($element->properties() as $key => $value) {
            $specification = $declared[$key] ?? null;

            if ($specification === null) {
                continue;
            }

            $type = $specification->type();

            if ($type->admits($value)) {
                continue;
            }

            $violations[] = new Violation(
                ViolationCode::MismatchedPropertyType,
                $element->id,
                (string) $key,
                \sprintf(
                    'Property "%s" is declared as "%s" but carries a value of type "%s".',
                    $key,
                    $type->describe(),
                    get_debug_type($value->jsonSerialize()),
                ),
            );
        }

        return $violations;
    }

    /**
     * A language map entry keyed by an id no `language` row carries. It is a warning rather than an error:
     * key existence is not a write constraint, reduction never selects a key outside the request's language
     * chain, and the layout serves correctly with the entry sitting unread.
     *
     * Only a map variant is walked. A bare string, a list (the wire shape of an empty map included) and the
     * null variant are wrong shapes for a translatable property, already reported as
     * {@see ViolationCode::MismatchedPropertyType}, and carry no language keys.
     *
     * @param array<string, PropertySpecification>|null $declared the component's declared properties, or null when unregistered
     * @param \Closure(): array<string, true> $languageIds
     *
     * @return list<Violation>
     */
    private function danglingLanguageViolations(StoredElement $element, ?array $declared, \Closure $languageIds): array
    {
        if ($declared === null) {
            return [];
        }

        $violations = [];

        foreach ($element->properties() as $key => $value) {
            $specification = $declared[$key] ?? null;

            if ($specification === null || !$specification->type()->translatable()) {
                continue;
            }

            if (!$value->isMap()) {
                continue;
            }

            foreach (array_keys($value->asMap()) as $rawKey) {
                $languageId = (string) $rawKey;

                if (\array_key_exists($languageId, $languageIds())) {
                    continue;
                }

                $violations[] = new Violation(
                    ViolationCode::DanglingLanguage,
                    $element->id,
                    (string) $key,
                    \sprintf('Property "%s" carries a translation for language "%s", which does not exist.', $key, $languageId),
                );
            }
        }

        return $violations;
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
        $property = $this->declaredProperty($component, $key);

        if ($property === null) {
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
     * A provider whose key is consumed by no descendant element is orphaned (warning, non-blocking). A
     * root-scoped consumer does not count as consuming it: that consumer takes its value from the layout's
     * root-ambient set, so the provider under the same key feeds nothing.
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
                if ($consumer->scope === ConsumerScope::Root) {
                    continue;
                }

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
            // Satisfied iff the element holds a value for the key under the rule {@see hasStoredValue()} states
            // — for a translatable property, its language map carrying the anchor entry. Serving applies no
            // type default, so only a stored value renders. The type default is a creation-time seed (scaffold
            // + the write-boundary seeder), not a render-time fallback, and so is not consulted here. A stored
            // explicit null counts as no value (it renders empty), so a required primitive authored as null is
            // reported unresolved.
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
     * way. Emptiness is the same rule the strict primitive check above uses ({@see hasStoredValue()}): a stored
     * explicit null counts as no value, and a translatable property counts as filled only through its anchor
     * entry.
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
     * The rule is type-aware, so the key's declaration is read through the element-type registry; an
     * unregistered component and an undeclared key both take the untranslated rule.
     *
     * Untranslated, a value counts when the key is present AND its variant is not null.
     * {@see StoredElement::property()} separates the two empty cases the older model conflated: `null` means the
     * key is absent, while an authored explicit null comes back as a present stored value answering true to
     * `isNull()`. Both are "no value", so a single-term `property($key) === null` test would silently credit an
     * authored null.
     *
     * Translatable, a value counts when its language map carries the anchor entry `Defaults::LANGUAGE_SYSTEM`
     * and that entry's value is a string variant. The anchor terminates every language chain a
     * `SalesChannelContext` is built with, so an anchor entry resolves on every request while any other entry
     * may not. An empty string satisfies, as it does for any other primitive. An anchor entry holding the null
     * variant does not satisfy and an absent anchor key does not satisfy; the two are distinct stored states
     * that this rule maps to the same answer.
     */
    private function hasStoredValue(StoredElement $element, string $key): bool
    {
        $value = $element->property($key);

        if ($value === null) {
            return false;
        }

        if (!$this->isTranslatableProperty($element->component, $key)) {
            return !$value->isNull();
        }

        $raw = $value->jsonSerialize();

        // A list variant cannot carry the anchor key, so recognising the map variant separately would add a
        // term this lookup already decides.
        return \is_array($raw) && \is_string($raw[Defaults::LANGUAGE_SYSTEM] ?? null);
    }

    /**
     * The component's declared-property map, or null when the registry does not know the component. The one
     * has-guarded registry read the property lookups share; every caller treats the null as "declares nothing"
     * rather than reporting it — the unregistered case is {@see intrinsicElementViolations()}'s violation.
     *
     * @return array<string, PropertySpecification>|null
     */
    private function declaredProperties(string $component): ?array
    {
        if (!$this->registry->has($component)) {
            return null;
        }

        return $this->registry->get($component)->properties();
    }

    /**
     * The declared property for a component's key, or null when the component is unregistered, the key is not
     * declared, or the entry is not a {@see PropertySpecification}.
     */
    private function declaredProperty(string $component, string $key): ?PropertySpecification
    {
        $properties = $this->declaredProperties($component);

        if ($properties === null) {
            return null;
        }

        $property = $properties[$key] ?? null;

        return $property instanceof PropertySpecification ? $property : null;
    }

    private function isTranslatableProperty(string $component, string $key): bool
    {
        return $this->declaredProperty($component, $key)?->type()->translatable() ?? false;
    }

    /**
     * True only for a single-primitive declared type: a union answers false here even though the serving
     * side treats every non-reference declaration as authored ({@see RenderedElementFactory}). The one
     * consequence is keying — a union-typed configured input property takes the reference-property
     * fallback at the call site instead of being keyed on itself.
     */
    private function isDeclaredPrimitiveProperty(string $component, string $key): bool
    {
        return $this->declaredProperty($component, $key)?->type()->isPrimitive() ?? false;
    }

    /**
     * Candidates the resolver could actually select: a parent (received-context) provider, a root-ambient offer,
     * or a loader whose config is complete. Incomplete loaders cannot be picked, so 0 context offers + N
     * incomplete loaders is unresolved, not ambiguous.
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
     * A required consumer whose key nothing in scope supplies. The two scopes draw from disjoint halves of the
     * available set; without the split a required parent-scope consumer for a root-only key would pass this
     * gate and then render nothing.
     *
     * @param list<ProvidedContext> $available
     *
     * @return list<Violation>
     */
    private function brokenChainViolations(StoredElement $element, array $available): array
    {
        $violations = [];

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$consumer->required || $this->isSatisfied($available, (string) $consumerKey, $consumer->scope)) {
                continue;
            }

            $message = $consumer->scope === ConsumerScope::Root
                ? \sprintf('Required root-scoped context "%s" is supplied by no bound source.', $consumerKey)
                : \sprintf('Required context "%s" is provided by no ancestor.', $consumerKey);

            $violations[] = new Violation(
                ViolationCode::BrokenRequiredChain,
                $element->id,
                (string) $consumerKey,
                $message,
            );
        }

        return $violations;
    }

    /**
     * One matching rule for both scopes: an available entry supplies a consumer when its key is the consumer key
     * or the base of the consumer's dot path ({@see ContextPathResolver::matches()}, the same predicate delivery
     * matches on, so a required dotted consumer delivery would resolve is not reported broken). The entry must
     * come from the half the consumer's scope draws on: a `Parent` consumer takes only element-provided entries,
     * a `Root` consumer only root-ambient ones.
     *
     * @param list<ProvidedContext> $available
     */
    private function isSatisfied(array $available, string $consumerKey, ConsumerScope $scope): bool
    {
        $wantsRoot = $scope === ConsumerScope::Root;

        foreach ($available as $provided) {
            if ($provided->root !== $wantsRoot) {
                continue;
            }

            if ($this->contextPathResolver->matches($provided->contextKey, $consumerKey)) {
                return true;
            }
        }

        return false;
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
