<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Index;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * Mints the {@see ResolvedValueIndex} for one finished rendered forest: every rendered property key of every
 * element gets a ref, equal values share one, and the assignment map says which key points where.
 *
 * TRAVERSAL is pre-order DFS — an element, then its slots in the slot map's insertion order, and within a
 * slot its children in list order. Ref numbers therefore run in document order, which is the only reason the
 * order is fixed at all: nothing outside may assert a literal ref id.
 *
 * PER-ELEMENT EMISSION ORDER is the grammar, and {@see emissionOrder()} is the one place it is stated:
 * declared primitives in the element type's declared property order, then loader-resolved, delivered-context,
 * distribution-referenced and injected keys, each of those four in byte order of the key name. Byte order
 * means `strcmp`, so a numeric-looking key sorts as the string it is rather than being renumbered the way
 * PHP's default sort would.
 *
 * COVERAGE is total over the finished tree. A key on the element with no provenance entry is
 * {@see ValueOrigin::Injected} rather than an error, and a provenance entry for a key the element no longer
 * carries is ignored — a finishing step or a finalization listener may drop a node or a key after provenance
 * was recorded, and neither is a fault. Each key appears exactly once, in the slot of the origin its
 * provenance names.
 *
 * CATEGORY SEMANTICS, the part a later reader is most likely to get wrong:
 *
 * - A key's category comes from its provenance entry, not from its value's history. A finalization listener
 *   rewriting a loader-resolved key leaves that key `LoaderResolved`; the ref then describes the finished
 *   value, and the invariant that holds is one key, one ref, one assignment entry.
 * - A key with no provenance entry takes `Injected`. That is the only way a key becomes injected.
 * - The factory assigns categories by walking the provenance map rather than by tracking key history: the key
 *   set comes from the element, each key's category comes from that key's entry in the provenance map, and no
 *   record of what a key held earlier is kept or consulted.
 *
 * DEDUP runs off three lookups. Which one a key uses is decided by its origin, and for a loader-resolved key
 * by one further question: is the finished value still the one the loader returned?
 *
 * - the loader-identity map, keyed on `source|configHash|<identity>` and consulted for `LoaderResolved` keys
 *   only. `<identity>` is the value's `getApiAlias()` and `getUniqueIdentifier()` together for an {@see Entity}
 *   — a uid is unique within an entity type and not across types, so the type is part of the identity — and the
 *   provenance's `inputsHash` otherwise. This rule exclusively owns genuine loader output: the key decides
 *   alone, a hit reusing the ref it names and a miss minting a fresh one, and no values are compared on
 *   either path, because two loads of one thing never hand back one instance — the DAL keeps
 *   no identity map, `EntityHydrator::$hydrated` being reset per `hydrate()` call — and neither instance
 *   identity nor value equality can then collapse them. That is what makes two elements loading the same
 *   entity share a ref, and equally what makes two elements loading the same collection, tree or listing
 *   result under one source, config and inputs share a ref.
 * - the instance map, `spl_object_id()` => ref, consulted for every object value the loader rule does not own
 *   and written by every ref an object value receives, genuine loader output included. This one is
 *   load-bearing: a context delivery that broadcasts a provider's value hands the child the same PHP
 *   instance — a broadcast is an `array_fill` of one value, and no distribution strategy and no dotted-path
 *   resolution mints an object — so consulting the instance map makes a delivered value share the provider's
 *   ref exactly when it *is* the provider's value. A transformed delivery (keyed, indexed, sliced, iterator,
 *   dotted) is a different value and gets a different ref with no special-casing anywhere.
 * - the value map, for non-object non-null values, comparing by the value equality
 *   {@see StoredValue::equals()} defines: scalars by
 *   `===`, lists positionally, maps per key with key order irrelevant, and a list never equal to a map.
 *
 * A loader-resolved key whose finished value is NOT the loader's own — a finalization listener replaced it,
 * caught by {@see LoaderValueIdentity::$producedFingerprint} — dedups as a non-loader value instead, through
 * the instance or value map. It keeps its `LoaderResolved` category and its emission slot; only its dedup rule
 * changes, because the identity describes a value that is no longer there.
 *
 * Null is the single case where the origin rule does not govern: every explicit null shares one null-valued
 * ref, whatever produced it. A null carries no identity to key on, ordinary value equality would unify two
 * nulls anyway, and the distinction that matters is preserved elsewhere — the PRESENCE of an assignment entry
 * is what separates "a resolution ran and found nothing" from "nothing wrote here". Two null entries a client
 * cannot tell apart are redundant wire.
 *
 * @internal
 *
 * @phpstan-type IndexState array{
 *     data: array<string, mixed>,
 *     assignments: array<string, array<string, string>>,
 *     loaderRefs: array<string, string>,
 *     instanceRefs: array<int, string>,
 *     valueRefs: list<array{value: mixed, ref: string}>,
 *     nullRef: string|null,
 *     seen: array<string, true>,
 *     next: int
 * }
 */
#[Package('framework')]
final readonly class ResolvedValueIndexFactory
{
    public function __construct(
        private AbstractContentSystemElementTypeRegistry $typeRegistry,
        private ValueFingerprinter $fingerprinter,
    ) {
    }

    /**
     * @param list<RenderedElement> $tree the finished rendered forest, after every finishing step and the
     *                                    finalization event have run
     * @param array<string, array<string, ValueProvenance>> $provenance element id => property key => provenance
     */
    public function create(array $tree, array $provenance): ResolvedValueIndex
    {
        /** @var IndexState $state */
        $state = [
            'data' => [],
            'assignments' => [],
            'loaderRefs' => [],
            'instanceRefs' => [],
            'valueRefs' => [],
            'nullRef' => null,
            'seen' => [],
            'next' => 1,
        ];

        foreach ($tree as $element) {
            $this->visit($element, $provenance, $state);
        }

        return new ResolvedValueIndex($state['data'], $state['assignments']);
    }

    /**
     * An element with no rendered properties contributes no assignment entry at all, rather than an empty map:
     * the assignment map answers "which refs does this element point at", and an element pointing at none has
     * nothing to say. That is also what the extraction path this replaces does.
     *
     * @param array<string, array<string, ValueProvenance>> $provenance
     * @param IndexState $state
     */
    private function visit(RenderedElement $element, array $provenance, array &$state): void
    {
        $this->rejectRepeatedId($element->id, $state);

        $elementProvenance = $provenance[$element->id] ?? [];
        $assignments = [];

        foreach ($this->emissionOrder($element, $elementProvenance) as $key) {
            $assignments[$key] = $this->refFor(
                $element->properties[$key],
                $elementProvenance[$key] ?? null,
                $state
            );
        }

        if ($assignments !== []) {
            $state['assignments'][$element->id] = $assignments;
        }

        foreach ($element->slots as $children) {
            foreach ($children as $child) {
                $this->visit($child, $provenance, $state);
            }
        }
    }

    /**
     * Two elements sharing an id would merge their assignments into one entry and serve each other's values,
     * so the walk rejects the second one. Element ids are unique across a forest by contract and the DAL write
     * enforces it, but the read path validates nothing, so this stays a second-layer assertion for DIRECT
     * callers of the factory. Reached through `ContentPipeline` it is unreachable: the pipeline rejects a
     * repeated id in the finished forest right after the finalization event and before it calls this factory,
     * so a listener's duplicate fails there first.
     *
     * Kept rather than deleted on purpose. It is an assertion, not dead code: the factory is a public seam a
     * caller can reach without the pipeline in front of it, and the corruption it names is silent.
     *
     * @param IndexState $state
     */
    private function rejectRepeatedId(string $elementId, array &$state): void
    {
        if (isset($state['seen'][$elementId])) {
            throw ContentSystemException::duplicateElementId($elementId);
        }

        $state['seen'][$elementId] = true;
    }

    /**
     * The grammar, stated once. The five members are disjoint and their union is every rendered key, so the
     * concatenation below is a permutation of `array_keys($element->properties)`.
     *
     * @param array<string, ValueProvenance> $elementProvenance
     *
     * @return list<string>
     */
    private function emissionOrder(RenderedElement $element, array $elementProvenance): array
    {
        return [
            ...$this->declaredOrder(
                $element->component,
                $this->keysWithOrigin($element, $elementProvenance, ValueOrigin::DeclaredAuthored)
            ),
            ...$this->byteOrder($this->keysWithOrigin($element, $elementProvenance, ValueOrigin::LoaderResolved)),
            ...$this->byteOrder($this->keysWithOrigin($element, $elementProvenance, ValueOrigin::DeliveredContext)),
            ...$this->byteOrder($this->keysWithOrigin($element, $elementProvenance, ValueOrigin::DistributionReferenced)),
            ...$this->byteOrder($this->keysWithOrigin($element, $elementProvenance, ValueOrigin::Injected)),
        ];
    }

    /**
     * The key set comes from the element and the category from the provenance map, which is why a provenance
     * entry naming a key the element no longer carries contributes nothing here.
     *
     * @param array<string, ValueProvenance> $elementProvenance
     *
     * @return list<string>
     */
    private function keysWithOrigin(RenderedElement $element, array $elementProvenance, ValueOrigin $origin): array
    {
        $keys = [];

        foreach (array_keys($element->properties) as $key) {
            if (($elementProvenance[$key]->origin ?? ValueOrigin::Injected) === $origin) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Type-spec order, not byte order: the declared property map is ordered as the type declares it, and that
     * is the order a client reading the type spec expects its primitives in.
     *
     * A key whose provenance says `DeclaredAuthored` while the type declares nothing under that name has no
     * declared position to take — an unregistered component declares nothing at all, the virtual root being
     * exactly that case — so it follows the declared ones in byte order rather than failing the render.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function declaredOrder(string $component, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $ordered = [];
        foreach (array_keys($this->declaredProperties($component)) as $declared) {
            if (\in_array($declared, $keys, true)) {
                $ordered[] = $declared;
            }
        }

        return [...$ordered, ...$this->byteOrder(array_values(array_diff($keys, $ordered)))];
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function byteOrder(array $keys): array
    {
        usort($keys, static fn (string $first, string $second): int => strcmp($first, $second));

        return $keys;
    }

    /**
     * Reads the same way {@see RenderedElementFactory} does,
     * including that a component naming no registered type declares nothing rather than failing.
     *
     * @return array<string, PropertySpecification>
     */
    private function declaredProperties(string $component): array
    {
        if (!$this->typeRegistry->has($component)) {
            return [];
        }

        return $this->typeRegistry->get($component)->properties();
    }

    /**
     * Keys off the identity's presence rather than the origin: {@see ValueProvenance} admits an identity for
     * `LoaderResolved` and only for it, so the two tests are the same test and this one narrows the type.
     *
     * @param IndexState $state
     */
    private function refFor(mixed $value, ?ValueProvenance $provenance, array &$state): string
    {
        $identity = $provenance?->loaderIdentity;

        if ($identity !== null) {
            return $this->loaderRef($value, $identity, $state);
        }

        return $this->valueRef($value, $state);
    }

    /**
     * The gate first: a finished value whose fingerprint differs from the one recorded when the loader
     * returned it is not the loader's value any more, so the identity says nothing about it and it dedups as
     * an ordinary value. That is a permitted listener action, never an error.
     *
     * Past the gate the identity key is the sole authority. It is not compared against the value the ref
     * carries, because that comparison is exactly what cannot work here: two loads of one thing yield two
     * instances, so any value-level test would refuse the dedup this rule exists to perform.
     *
     * Which is why a miss MINTS rather than handing the value to {@see valueRef()}: that path decides by the
     * value, and two loader-resolved values whose keys differ are two resolutions whatever their values look
     * like — one loader source resolving two inputs to the same string is not one resolution, and collapsing
     * the pair would make the second element's key serve the first's resolution. The mint still registers an
     * object in the instance map, so a later delivery of that very instance keeps sharing this ref.
     *
     * A null never reaches the key. It is a value with no identity, and every null shares one ref regardless
     * of origin — registering the shared null ref under an identity key would hand it to the next element
     * whose loader resolved that same source, config and inputs to something that is not null.
     *
     * @param IndexState $state
     */
    private function loaderRef(mixed $value, LoaderValueIdentity $identity, array &$state): string
    {
        if ($this->fingerprinter->fingerprint($value) !== $identity->producedFingerprint) {
            return $this->valueRef($value, $state);
        }

        if ($value === null) {
            return $this->nullRef($state);
        }

        $identityKey = \sprintf(
            '%s|%s|%s',
            $identity->source,
            $identity->configHash,
            $value instanceof Entity
                ? 'entity:' . $value->getApiAlias() . ':' . $value->getUniqueIdentifier()
                : 'inputs:' . $identity->inputsHash
        );

        $known = $state['loaderRefs'][$identityKey] ?? null;

        if ($known !== null) {
            $this->rememberInstance($value, $known, $state);

            return $known;
        }

        $ref = $this->mintRef($value, $state);
        $state['loaderRefs'][$identityKey] = $ref;

        return $ref;
    }

    /**
     * The dedup path for a value the loader rule does not own: an object by instance, a null by the one shared
     * null ref, anything else by value equality.
     *
     * @param IndexState $state
     */
    private function valueRef(mixed $value, array &$state): string
    {
        if (\is_object($value)) {
            $known = $state['instanceRefs'][spl_object_id($value)] ?? null;

            return $known === null ? $this->mintRef($value, $state) : $this->reuse($known, $value, $state);
        }

        if ($value === null) {
            return $this->nullRef($state);
        }

        foreach ($state['valueRefs'] as $candidate) {
            if ($this->valuesEqual($candidate['value'], $value)) {
                return $this->reuse($candidate['ref'], $value, $state);
            }
        }

        return $this->mintRef($value, $state);
    }

    /**
     * The dedup guard: a lookup that hands back an existing ref must hand back one already carrying this very
     * value, because a ref carrying two different values means one of the two keys pointing at it serves the
     * other's data.
     *
     * It cannot fire today, and it deliberately does not guard every reuse. The two lookups it does guard
     * decide by the value itself — instance identity, then value equality — so a hit already carries this
     * value. The loader-identity map bypasses it on purpose: its key, not the value, is the authority for
     * genuine loader output, and the whole point there is to reuse a ref carrying a different *instance* of
     * the same thing. So this is an assertion on the two value-deciding lookups rather than dead code: one
     * added later without a matching registration fails here instead of silently overwriting a served value.
     *
     * @param IndexState $state
     */
    private function reuse(string $ref, mixed $value, array $state): string
    {
        if (!$this->valuesEqual($state['data'][$ref], $value)) {
            throw ContentSystemException::invalidMapValue(
                'Resolved value index data',
                $ref,
                'the value this ref already carries',
                get_debug_type($value)
            );
        }

        return $ref;
    }

    /**
     * The one ref every explicit null in the response shares, minted on first sight.
     *
     * @param IndexState $state
     */
    private function nullRef(array &$state): string
    {
        $ref = $state['nullRef'];

        if ($ref === null) {
            $ref = $this->mintRef(null, $state);
            $state['nullRef'] = $ref;
        }

        return $ref;
    }

    /**
     * Refs are `r1`, `r2`, … in the order distinct values are first seen, and every mint registers the value
     * in whichever lookup can find it again.
     *
     * @param IndexState $state
     */
    private function mintRef(mixed $value, array &$state): string
    {
        $ref = 'r' . $state['next'];
        ++$state['next'];
        $state['data'][$ref] = $value;
        $this->rememberInstance($value, $ref, $state);

        if (!\is_object($value) && $value !== null) {
            $state['valueRefs'][] = ['value' => $value, 'ref' => $ref];
        }

        return $ref;
    }

    /**
     * First ref wins. An instance that already has one keeps it, so the instance map never repoints a value
     * that other keys are already pointing at through it.
     *
     * @param IndexState $state
     */
    private function rememberInstance(mixed $value, string $ref, array &$state): void
    {
        if (!\is_object($value)) {
            return;
        }

        $state['instanceRefs'][spl_object_id($value)] ??= $ref;
    }

    /**
     * The value map's equality, mirroring
     * {@see StoredValue::equals()}: scalars by `===`,
     * lists positionally, maps per key with key order irrelevant, and a list never equal to a map. Rendered
     * values are raw PHP rather than wrapped, so they can hold an object where a stored value cannot; a nested
     * object compares by identity, which is the same rule objects follow at the top level.
     */
    private function valuesEqual(mixed $first, mixed $second): bool
    {
        if (\is_array($first) && \is_array($second)) {
            return $this->arraysEqual($first, $second);
        }

        return $first === $second;
    }

    /**
     * @param array<array-key, mixed> $first
     * @param array<array-key, mixed> $second
     */
    private function arraysEqual(array $first, array $second): bool
    {
        if (\count($first) !== \count($second)) {
            return false;
        }

        if (array_is_list($first) !== array_is_list($second)) {
            return false;
        }

        foreach ($first as $key => $value) {
            if (!\array_key_exists($key, $second)) {
                return false;
            }

            if (!$this->valuesEqual($value, $second[$key])) {
                return false;
            }
        }

        return true;
    }
}
