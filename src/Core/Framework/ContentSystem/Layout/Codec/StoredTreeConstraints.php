<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One recursive constraint descriptor over the stored forest's wire shape: the list of root element arrays and,
 * through each element's slots, every element below them.
 *
 * The recursion is expressed as a lazy {@see Callback} re-entry rather than a nested constraint literal. A slot's
 * children carry the same element constraints as their parent, so composing them eagerly would not terminate;
 * {@see validateSlotElements()} rebuilds them at validation time instead and re-runs the validator on each child,
 * re-pathing the resulting violations onto the child's own index so a defect deep in the tree is still reported
 * where it sits.
 *
 * Style is the one part this class is registry-aware for: {@see build()} derives the per-option constraints from
 * the style option registry's current set on every call, so an app install, update or activation that changed the
 * set takes effect on the next write without a process restart. An option the registry does not know is rejected,
 * never repaired. The one other registry-aware rule, {@see PropertyTypeConformance}, is only attached here and
 * reads the element-type registry in its own validator, so this class stays blind to component types.
 *
 * Tree-global invariants are deliberately absent: this descriptor sees one element at a time and cannot decide
 * whether an id repeats across the forest.
 *
 * The context-wiring cluster — both wiring maps and the rule spanning them — lives in
 * {@see StoredTreeWiringConstraints}, which this class composes and whose constraints it attaches at the two
 * field positions and, for the cross-map rule, whole-element in {@see elementConstraints()}.
 *
 * Anything this descriptor accepts, {@see StoredElementCodec} must be able to decode, or a payload passes the
 * write and then throws on every later read. Two composition helpers keep that agreement from being forgotten
 * one field at a time: {@see nonNull()} pairs a value's type assertion with its null rejection, and
 * {@see stringKeyedMap()} pairs a wiring map's container check with its key-type check. The agreement itself is
 * pinned payload by payload by StoredTreeShapeConformanceTest, which runs both sides over one table.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreeConstraints
{
    private readonly StoredTreeWiringConstraints $wiring;

    public function __construct(
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
        private readonly StyleOptionConstraintDeriver $deriver,
    ) {
        $this->wiring = new StoredTreeWiringConstraints();
    }

    /**
     * @return list<Constraint>
     */
    public function build(): array
    {
        return $this->nonNull(
            new Type('array'),
            new Callback($this->validateSequentialListShape(...)),
            new All($this->elementConstraints(0)),
        );
    }

    /**
     * The `NotNull` at the head covers both places an element is reached — the forest's own `All` and
     * {@see validateSlotElements()} — so a null where an element belongs is rejected once, not per site.
     *
     * `$depth` is this element's own nesting depth, zero for a root — the same count
     * {@see StoredElementCodec::decode()} passes to `decodeElement()`. It is threaded into
     * {@see slotConstraints()} so a child reached through `slots` is built one level deeper, matching the
     * depth {@see StoredElementCodec::decodeSlots()} passes when it re-enters `decodeElement()`.
     *
     * @return list<Constraint>
     */
    private function elementConstraints(int $depth): array
    {
        return [
            new NotNull(),
            new Type('array'),
            new Collection(
                fields: [
                    'id' => [new NotBlank(), new Type('string'), new Callback($this->validateElementIdDomain(...))],
                    'component' => [new NotBlank(), new Type('string')],
                    'properties' => new Optional($this->stringKeyedMap(new Callback($this->validatePropertyValueDepth(...)))),
                    'dataRequirements' => new Optional($this->dataRequirementConstraints()),
                    'slots' => new Optional($this->slotConstraints($depth)),
                    'providesContext' => new Optional($this->wiring->contextProviderConstraints()),
                    'acceptsContext' => new Optional($this->wiring->contextConsumerConstraints()),
                    'style' => new Optional($this->styleConstraints()),
                    'attributedSpecifications' => new Optional($this->stringKeyedMap(...$this->nonNull(new Type('string')))),
                ],
                allowExtraFields: false,
                allowMissingFields: false
            ),
            // Whole-element, because the declaration a property value is judged against is reached through this
            // element's own `component`; the `Collection` above sees one field at a time and cannot pair them.
            new PropertyTypeConformance(),
            // Whole-element for the same reason: the rule spans `acceptsContext` and `providesContext`, and
            // neither the `Collection` above nor a per-entry constraint sees both maps.
            new Callback($this->wiring->validateRedistributeProviderConflicts(...)),
        ];
    }

    /**
     * Symfony's `Type`, `Collection` and `All` all skip a null value, so a type assertion on its own admits a
     * null that decode rejects. Every value whose decode counterpart requires one is built through here.
     *
     * @return list<Constraint>
     */
    private function nonNull(Constraint ...$constraints): array
    {
        return array_values([new NotNull(), ...$constraints]);
    }

    /**
     * One string-keyed wiring map: the container itself, its key types, and — when given — the constraints
     * every entry carries. Decode rejects a non-string key in each of these maps, through
     * {@see StoredElement}'s constructor, through
     * {@see StoredElementCodec}'s own key checks, or through its `stringKeyed()` guard, so pairing the key
     * check with the container check here keeps a map added later in step by construction.
     *
     * @return list<Constraint>
     */
    private function stringKeyedMap(Constraint ...$entryConstraints): array
    {
        $constraints = [new Type('array'), new Callback($this->validateStringKeys(...))];

        if ($entryConstraints !== []) {
            $constraints[] = new All($entryConstraints);
        }

        return $constraints;
    }

    /**
     * @return list<Constraint>
     */
    private function slotConstraints(int $depth): array
    {
        return $this->stringKeyedMap(
            ...$this->nonNull(
                new Type('array'),
                new Callback($this->validateSequentialListShape(...)),
                new Callback(fn (mixed $value, ExecutionContextInterface $context) => $this->validateSlotElements($value, $context, $depth)),
            )
        );
    }

    /**
     * Matches the codec's own non-string key rejection. PHP maps a numeric JSON member name back to an
     * integer array key, so this rejects both `{"12": …}` and a payload built with an integer key.
     */
    private function validateStringKeys(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            if (\is_string($key)) {
                continue;
            }

            $context->buildViolation('This map must be keyed by strings, {{ key }} is not a string key.')
                ->setParameter('{{ key }}', (string) $key)
                ->addViolation();
        }
    }

    /**
     * Matches the codec's own {@see StoredTreeCodec} / {@see StoredElementCodec} `array_is_list()` gate, so
     * nothing accepted here can fail decode on shape alone: an associative array — including the natural
     * decode of a JSON object sent where a JSON array was expected — reports a violation instead of passing
     * write validation and only failing on a later read.
     */
    private function validateSequentialListShape(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        if (!array_is_list($value)) {
            $context->buildViolation('This value should be a list with sequential, zero-based integer keys.')
                ->addViolation();
        }
    }

    /**
     * The lazy re-entry point of the recursion: the element constraints are built here, at validation time, so
     * building the descriptor itself terminates.
     *
     * `$depth` is the parent element's own depth, so the children reached through this slot sit one level
     * deeper — the same arithmetic {@see StoredElementCodec::decodeSlots()} applies when it calls
     * `decodeElement($child, $depth + 1)`. A child depth past {@see StoredElementCodec::MAX_NESTING_DEPTH}
     * is rejected here without building or validating that child's own constraints, matching the codec's
     * guard at the top of `decodeElement()`, which throws before looking at the child's shape at all. An
     * empty children list never reaches that guard: {@see StoredElementCodec} only ever evaluates it while
     * recursing into an actual child, so a slot with no children is never a depth violation, however deep it
     * sits.
     */
    private function validateSlotElements(mixed $value, ExecutionContextInterface $context, int $depth): void
    {
        if (!\is_array($value)) {
            return;
        }

        if ($value === []) {
            return;
        }

        $childDepth = $depth + 1;

        if ($childDepth > StoredElementCodec::MAX_NESTING_DEPTH) {
            $context->buildViolation('This value exceeds the maximum element nesting depth of {{ max }} levels.')
                ->setParameter('{{ max }}', (string) StoredElementCodec::MAX_NESTING_DEPTH)
                ->addViolation();

            return;
        }

        $elementConstraints = $this->elementConstraints($childDepth);

        foreach ($value as $index => $elementData) {
            $violations = $context->getValidator()->validate($elementData, $elementConstraints);

            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $path = "[$index]" . ($propertyPath !== '' ? ".$propertyPath" : '');

                $context->buildViolation((string) $violation->getMessage())
                    ->atPath($path)
                    ->addViolation();
            }
        }
    }

    /**
     * Bounds a single property's value the same way {@see StoredElementCodec::decodeValue()} does: the value
     * handed to this callback is the whole payload for one `properties` entry, at depth zero exactly as
     * `decodeProperties()` calls `decodeValue($value, $path, 0)`, and only an array value carries any further
     * nesting to bound.
     */
    /**
     * The write-side expression of the id value domain {@see StoredElementCodec::decodeElement()} admits. Both
     * sides must state it: `NotBlank` exempts `'0'` and `Type` admits the reserved literal, so without this the
     * descriptor would accept a payload decode refuses — a row persisted once and unreadable ever after.
     */
    private function validateElementIdDomain(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_string($value)) {
            return;
        }

        if ($value === VirtualRootWrapper::VIRTUAL_ROOT_ID) {
            $context->buildViolation('This value is the reserved virtual-root id.')->addViolation();

            return;
        }

        if (!\is_string(array_key_first([$value => null]))) {
            $context->buildViolation('This value is a string PHP casts to an integer array key.')->addViolation();
        }
    }

    private function validatePropertyValueDepth(mixed $value, ExecutionContextInterface $context): void
    {
        if (!$this->exceedsMaxValueNestingDepth($value, 0)) {
            return;
        }

        $context->buildViolation('This value exceeds the maximum property value nesting depth of {{ max }} levels.')
            ->setParameter('{{ max }}', (string) StoredElementCodec::MAX_NESTING_DEPTH)
            ->addViolation();
    }

    /**
     * Mirrors {@see StoredElementCodec::decodeValue()}'s own guard exactly: a non-array value never carries
     * further nesting and is never bounded, and the depth check on an array value runs before descending into
     * its items, so a value whose deepest array sits past the bound is rejected regardless of what its
     * shallower levels look like.
     */
    private function exceedsMaxValueNestingDepth(mixed $value, int $depth): bool
    {
        if (!\is_array($value)) {
            return false;
        }

        if ($depth > StoredElementCodec::MAX_NESTING_DEPTH) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->exceedsMaxValueNestingDepth($item, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Constraint>
     */
    private function dataRequirementConstraints(): array
    {
        return $this->stringKeyedMap(
            ...$this->nonNull(
                new Type('array'),
                new Collection(
                    fields: [
                        'key' => new Optional([new Type('string')]),
                        'source' => [new NotBlank(), new Type('string')],
                        // Shape only, by design: what a config *means* is the source's own serializer's to judge, and the
                        // decode-first normalize() order is where it does so, before this descriptor ever runs.
                        'config' => new Optional($this->stringKeyedMap()),
                    ],
                    allowExtraFields: false,
                    allowMissingFields: false,
                ),
            )
        );
    }

    /**
     * A breakpoint-aware option takes a per-breakpoint map keyed by {@see Breakpoint::values()}; a flat option
     * takes a single scalar. The scalar type constraint rejects an array, so a flat option sent as a breakpoint
     * map is rejected here. The mirror case is not symmetric: on the write path the style normalizer runs first
     * and broadcasts a bare scalar across every breakpoint, so a breakpoint-aware option reaches this descriptor
     * already in its canonical map form.
     *
     * @return list<Constraint>
     */
    private function styleConstraints(): array
    {
        $optionFields = [];

        foreach ($this->registry->all() as $name => $specification) {
            $valueConstraints = $this->deriver->derive($specification->valueType());

            // NotNull at both levels, because every other constraint here skips null: `Type` returns for it,
            // and a boolean option carries no `NotBlank` by design. Without it the descriptor would admit a
            // payload decode refuses, which is the one direction the two sides may not diverge in.
            if (!$specification->breakpointAware()) {
                $optionFields[$name] = new Optional([new NotNull(), ...$valueConstraints]);

                continue;
            }

            $breakpointFields = [];
            foreach (Breakpoint::values() as $breakpoint) {
                $breakpointFields[$breakpoint] = new Optional([new NotNull(), ...$valueConstraints]);
            }

            $optionFields[$name] = new Optional([
                new NotNull(),
                new Type('array'),
                // An empty breakpoint map is rejected on both sides; decode throws rather than dropping it
                new Count(min: 1),
                new Collection(
                    fields: $breakpointFields,
                    allowExtraFields: false,
                    allowMissingFields: false,
                ),
            ]);
        }

        return [
            new Type('array'),
            new Collection(
                fields: $optionFields,
                allowExtraFields: false,
                allowMissingFields: false,
            ),
        ];
    }
}
