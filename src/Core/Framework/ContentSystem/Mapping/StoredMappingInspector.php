<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingInterpolator;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingToken;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * The admissibility rules for stored data mappings, in one place and reporting-agnostic: every mapping names
 * a mappable property, a catalogued path, and a value the
 * property can hold.
 *
 * {@see StoredMappingValidator} refuses the `content_layout` write, {@see LayoutDiagnostics} reports findings
 * on editor routes, and map mutations and default seeding use {@see admitProperty()} before storing a candidate.
 *
 * The rules are keyed on the ROOT SOURCE, not on the root-ambient context: a catalogue lookup needs the
 * source id, and a resolved context cannot supply one. That is the reason this is not simply another check
 * inside the analysis, and the reason both callers have to be handed the source explicitly.
 *
 * WHAT COUNTS AS A MAPPING is the load-bearing decision here. A mapping carries an explicit typed source reference;
 * {@see ContextConsumerMirror} never writes one for the root-scoped reference wiring it derives.
 *
 * That test lives in {@see MappingConsumers}, shared with the diagnostics layer so the two cannot drift.
 *
 * Ordinary root- and parent-scoped consumers remain outside these rules.
 *
 * TWO KINDS OF MAPPING ARE JUDGED HERE, and they are found in different places. A whole-field mapping is a context
 * CONSUMER carrying a typed source, so it is declared and can be enumerated. An INLINE mapping is a `{{map:path}}`
 * token inside an `inlineMappable` string PROPERTY: nothing declares it, so {@see inspectElementInline()} scans the
 * text for it. Mapping-like text in other properties is literal and is not inspected.
 *
 * The write gate and diagnose route share {@see inspect()}, so mappings in opted-in properties are judged by the
 * same rules. Preview reports mapping faults through diagnostics without blocking rendering.
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
        private readonly InlineMappingTokenParser $tokenParser,
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

        foreach ($this->flatten($roots) as $element) {
            foreach ($this->inspectElement($element, $rootSource) as $problem) {
                $problems[] = $problem;
            }

            foreach ($this->inspectElementInline($element, $rootSource) as $problem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /**
     * Whole-field mapping findings only, for structural replacement code that reports dropped consumers.
     * Inline-token diagnostics cannot represent dropped wiring because tokens are authored property text.
     *
     * @param list<StoredElement> $roots
     *
     * @return list<MappingProblem>
     */
    public function inspectWholeField(array $roots, string $rootSource): array
    {
        $problems = [];

        foreach ($this->flatten($roots) as $element) {
            foreach ($this->inspectElement($element, $rootSource) as $problem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /**
     * Inline tokens are explicit mapping intent even when no root source is bound. Report that missing
     * prerequisite without consulting a source-specific candidate catalogue. Whole-field mappings remain
     * unjudged here because their admissibility cannot be determined without that catalogue.
     *
     * @param list<StoredElement> $roots
     *
     * @return list<MappingProblem>
     */
    public function inspectInlineWithoutRootSource(array $roots): array
    {
        $problems = [];

        foreach ($this->flatten($roots) as $element) {
            if (!$this->typeRegistry->has($element->component)) {
                continue;
            }

            $declared = $this->typeRegistry->get($element->component)->properties();

            foreach ($element->properties() as $key => $value) {
                if (!$value->isString()) {
                    continue;
                }

                $text = $value->asString();
                if (!$this->tokenParser->containsToken($text)) {
                    continue;
                }

                $propertyKey = (string) $key;
                $property = $declared[$propertyKey] ?? null;
                if ($property === null || !$property->inlineMappable()) {
                    continue;
                }

                foreach ($this->tokenParser->parse($text) as $token) {
                    $exception = $token->malformed
                        ? ContentSystemException::malformedInlineMappingToken($token->path, $propertyKey)
                        : ContentSystemException::inlineMappingRequiresRootSource($token->path, $propertyKey);
                    $problems[] = new MappingProblem(
                        $element->id,
                        $propertyKey,
                        $token->path,
                        $exception,
                    );
                }
            }
        }

        return $problems;
    }

    /**
     * The admission decision used when creating a mapping or seeding a declared default. A successful
     * candidate supplies the exact source and context type that must be stored together.
     */
    public function admitProperty(string $component, string $propertyKey, MappingSourceReference $source, string $rootSource): MappingCandidate|ContentSystemException
    {
        if (!$this->typeRegistry->has($component)) {
            return ContentSystemException::propertyNotMappable($component, $propertyKey);
        }

        $property = $this->typeRegistry->get($component)->properties()[$propertyKey] ?? null;
        if ($property === null || !$property->mappable()) {
            return ContentSystemException::propertyNotMappable($component, $propertyKey);
        }

        return $this->propertyCandidate($component, $propertyKey, $property, $source, $rootSource);
    }

    /**
     * The complete decision for a stored whole-field mapping, including its persisted cardinality. Rendering
     * uses this same method as the write gate so a directly stored or stale layout cannot bypass admission.
     */
    public function admitStoredProperty(
        string $component,
        string $propertyKey,
        ContextType $contextType,
        MappingSourceReference $source,
        string $rootSource,
    ): MappingCandidate|ContentSystemException {
        $candidate = $this->admitProperty($component, $propertyKey, $source, $rootSource);
        if ($candidate instanceof ContentSystemException) {
            return $candidate;
        }

        if ($contextType !== $candidate->contextType) {
            return ContentSystemException::mappingContextTypeMismatch($source->displayName(), $contextType->value, $candidate->contextType->value);
        }

        return $candidate;
    }

    /**
     * The shared candidate and text-type decision for an inline token. The caller separately owns token placement
     * and the property's `inlineMappable` declaration, since both depend on the parsed token and target property.
     */
    public function admitInlinePath(string $path, string $rootSource): MappingCandidate|ContentSystemException
    {
        $candidate = $this->candidateRegistry->forRootSource($rootSource)[$path] ?? null;
        if ($candidate === null || $candidate->path !== $path) {
            return ContentSystemException::unknownInlineMappingPath($path, $rootSource);
        }

        if (!\in_array($candidate->valueType, InlineMappingInterpolator::STRINGIFIABLE_TYPES, true)) {
            return ContentSystemException::inlineMappingValueNotStringifiable($path, $candidate->valueType);
        }

        return $candidate;
    }

    public function isInlineMappable(string $component, string $propertyKey): bool
    {
        if (!$this->typeRegistry->has($component)) {
            return false;
        }

        $property = $this->typeRegistry->get($component)->properties()[$propertyKey] ?? null;

        return $property?->inlineMappable() ?? false;
    }

    /**
     * A non-null provider value is checked by the same compatibility owner used when its candidate is admitted.
     * Present null is valid resolved data; the provider result, rather than this type check, distinguishes it from no value.
     */
    public function acceptsResolvedValue(MappingCandidate $candidate, mixed $value): bool
    {
        return $value === null || $this->compatibility->admits($candidate->valueType, $value);
    }

    /**
     * @return list<MappingProblem>
     */
    private function inspectElement(StoredElement $element, string $rootSource): array
    {
        if (!$this->typeRegistry->has($element->component)) {
            // An unregistered component is already an intrinsic well-formedness violation; this rule set does
            // not report it a second time under a mapping code.
            return [];
        }

        $declared = $this->typeRegistry->get($element->component)->properties();
        $problems = [];

        foreach ($element->contextDefinitions->getAllConsumers() as $consumerKey => $consumer) {
            if (!$this->mappingConsumers->isMapping($consumer)) {
                continue;
            }

            $propertyKey = (string) $consumerKey;
            $property = $declared[$propertyKey] ?? null;

            if ($property === null) {
                $exception = ContentSystemException::propertyNotMappable($element->component, $propertyKey);
                $problems[] = new MappingProblem($element->id, $propertyKey, $consumer->source?->displayName() ?? (string) $consumerKey, $exception);

                continue;
            }

            $source = $consumer->source;
            if ($source === null) {
                continue;
            }

            $decision = $this->admitStoredProperty(
                $element->component,
                $propertyKey,
                $consumer->type,
                $source,
                $rootSource,
            );
            $exception = $decision instanceof ContentSystemException ? $decision : null;
            if ($exception === null) {
                continue;
            }

            $problems[] = new MappingProblem($element->id, $propertyKey, $consumer->source?->displayName() ?? (string) $consumerKey, $exception);
        }

        return $problems;
    }

    /**
     * The inline counterpart of {@see inspectElement()}, and it reads a different part of the element: a whole-field
     * mapping is a CONSUMER, an inline mapping is a `{{map:path}}` token inside a string PROPERTY. Nothing declares
     * one, which is why this has to scan the text.
     *
     * The shape rule matches what the render path expands — top-level string properties only, containers untouched
     * — because the gate and the expansion must agree on where a token counts. See
     * `Mapping\Inline\InlineMappingExpander` for why that boundary is where it is.
     *
     * @return list<MappingProblem>
     */
    private function inspectElementInline(StoredElement $element, string $rootSource): array
    {
        if (!$this->typeRegistry->has($element->component)) {
            return [];
        }

        $declared = $this->typeRegistry->get($element->component)->properties();
        $problems = [];

        foreach ($element->properties() as $key => $value) {
            if (!$value->isString()) {
                continue;
            }

            $text = $value->asString();

            if (!$this->tokenParser->containsToken($text)) {
                continue;
            }

            $propertyKey = (string) $key;
            $property = $declared[$propertyKey] ?? null;

            if ($property === null || !$property->inlineMappable()) {
                continue;
            }

            foreach ($this->tokenParser->parse($text) as $token) {
                $exception = $this->inlineFault(
                    $propertyKey,
                    $token,
                    $rootSource
                );

                if ($exception === null) {
                    continue;
                }

                $problems[] = new MappingProblem($element->id, $propertyKey, $token->path, $exception);
            }
        }

        return $problems;
    }

    /**
     * The inline rules for opted-in text: placement, catalogue membership, then the value's shape.
     */
    private function inlineFault(
        string $propertyKey,
        InlineMappingToken $token,
        string $rootSource,
    ): ?ContentSystemException {
        if ($token->malformed) {
            return ContentSystemException::malformedInlineMappingToken($token->path, $propertyKey);
        }

        if ($token->insideMarkup) {
            return ContentSystemException::inlineMappingTokenInMarkup($token->path, $propertyKey);
        }

        $decision = $this->admitInlinePath($token->path, $rootSource);

        return $decision instanceof ContentSystemException ? $decision : null;
    }

    private function propertyCandidate(
        string $component,
        string $propertyKey,
        ?PropertySpecification $property,
        MappingSourceReference $source,
        string $rootSource,
    ): MappingCandidate|ContentSystemException {
        if ($property === null || !$property->mappable()) {
            return ContentSystemException::propertyNotMappable($component, $propertyKey);
        }

        $sourceName = $source->displayName();
        $candidate = $this->candidateForSource($source, $rootSource, $this->candidateRegistry->forRootSource($rootSource));
        if ($candidate instanceof ContentSystemException) {
            return ContentSystemException::unknownMappingPath($sourceName, $rootSource);
        }

        $declaredType = $property->type()->type();

        if (
            !$this->compatibility->permits($declaredType, $candidate->valueType)
            || !\in_array($candidate->contextType->value, $property->type()->contextTypes(), true)
        ) {
            return ContentSystemException::mappingTypeMismatch(
                $propertyKey,
                \is_array($declaredType) ? implode('|', $declaredType) : $declaredType,
                $candidate->valueType,
            );
        }

        return $candidate;
    }

    /**
     * @param array<string, MappingCandidate> $candidates
     */
    private function candidateForSource(
        MappingSourceReference $source,
        string $rootSource,
        array $candidates,
    ): MappingCandidate|ContentSystemException {
        $candidate = $candidates[$source->displayName()] ?? null;

        return $candidate !== null && $candidate->source->isSameAs($source)
            ? $candidate
            : ContentSystemException::unknownMappingPath($source->displayName(), $rootSource);
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
