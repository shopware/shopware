<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedTreeEditor;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

/**
 * One element as rendering and the Store API see it, and the counterpart to {@see StoredElement} on the far
 * side of the storage/render split. It carries `id`, `component`, a flat property map, its slots and its
 * style, and nothing else: no data requirements, no context wiring and no attribution, because those
 * authoring concerns have finished their work before anything renders.
 *
 * Property values are raw PHP values rather than {@see StoredValue}s, hydrated entities included. That is
 * deliberate: the Twig filter chain needs the entity itself, so whatever produces a rendered element
 * unwraps at the seam and nothing re-wraps afterwards.
 *
 * Immutable — every edit produces a new instance through a `with*()` method, which is also the idiom a
 * rendering listener transforms a tree with; {@see RenderedTreeEditor} applies one across a whole forest.
 * Unlike the storage model, the constructor carries no mint-site restriction: anything may build one.
 *
 * What the constructor does check is the map keys, the slot shape and the property value domain. A malformed
 * slot map fails far from where it was built — as a `TypeError` inside {@see RenderedTreeEditor} or as a Twig
 * error while a `<twig:Slot>` iterates the children. A property value is admitted only if it is a scalar,
 * null, an array recursively of the same domain, a {@see Struct}, a `\DateTimeInterface` or a `\BackedEnum`;
 * anything else is rejected as a producer defect. That closes the concealment hole the output encoders
 * describe: a non-`Struct` object holding a `Struct` somewhere in its object graph would carry that `Struct`
 * past {@see StructEncoder::encode()}, the framework's protection gate, and publish every field of it.
 *
 * Because this is not a `Struct`, `StoreApiSeoResolver` cannot walk it through `getVars()` and instead
 * hardcodes `properties` and `slots` in a branch of its own. Those two fields and their value domains are
 * what that branch reads: renaming either, or admitting a value shape it does not descend, needs the branch
 * changed with it. The other three fields carry no `Struct` and are not read there.
 */
#[Package('framework')]
final readonly class RenderedElement
{
    /**
     * @param array<string, mixed> $properties
     * @param array<string, list<RenderedElement>> $slots
     */
    public function __construct(
        public string $id,
        public string $component,
        public array $properties = [],
        public array $slots = [],
        public ElementStyle $style = new ElementStyle(),
    ) {
        $this->rejectNumericPropertyKeys($properties);
        $this->rejectUnsupportedPropertyValues($properties);
        $this->rejectMalformedSlots($slots);
    }

    /**
     * Sets one key and leaves the rest of the map alone. A `null` value is a present property holding null,
     * which is how a lookup that ran and found nothing differs from one that never wrote here at all.
     */
    public function withProperty(string $key, mixed $value): self
    {
        $properties = $this->properties;
        $properties[$key] = $value;

        return $this->copy(properties: $properties);
    }

    /**
     * Replaces the whole property map rather than merging into it, matching {@see StoredElement::withProperties()}
     * so the two models do not differ in anything but the prefix. Use {@see withProperty()} to set one key.
     *
     * @param array<string, mixed> $properties
     */
    public function withProperties(array $properties): self
    {
        return $this->copy(properties: $properties);
    }

    /**
     * Goes through the constructor like every other edit, so a malformed slot map is rejected here too.
     *
     * @param array<string, list<RenderedElement>> $slots
     */
    public function withSlots(array $slots): self
    {
        return $this->copy(slots: $slots);
    }

    /**
     * The single place that enumerates every field, so adding one means touching this method and its own
     * `with*()` method only. A `null` argument means "not overridden": no field is nullable, so the
     * sentinel is unambiguous.
     *
     * @param array<string, mixed>|null $properties
     * @param array<string, list<RenderedElement>>|null $slots
     */
    private function copy(
        ?string $id = null,
        ?string $component = null,
        ?array $properties = null,
        ?array $slots = null,
        ?ElementStyle $style = null,
    ): self {
        return new self(
            $id ?? $this->id,
            $component ?? $this->component,
            $properties ?? $this->properties,
            $slots ?? $this->slots,
            $style ?? $this->style,
        );
    }

    /**
     * PHP casts a numeric-string array key to an integer, so rejecting integer keys rejects both `12` and
     * `'12'`. Property names are names, and unlike the slot map this one is not simply carried over from a
     * stored tree that already rejects numeric keys: a rendered property key may come from an element-type
     * declaration or from a context consumer's `propertyAlias`, neither of which is checked for numeric
     * keys upstream. The ban is therefore re-stated here rather than inherited.
     *
     * @param array<array-key, mixed> $properties
     */
    private function rejectNumericPropertyKeys(array $properties): void
    {
        foreach (array_keys($properties) as $key) {
            if (\is_int($key)) {
                throw ContentSystemException::invalidMapKey('Rendered element property map', 'int');
            }
        }
    }

    /**
     * Deliberately a second walk rather than an extension of {@see rejectNumericPropertyKeys()}, which stays
     * flat over the top-level map: this one descends into nested arrays, and a nested array may be a list
     * whose keys are integers by definition. The numeric-key ban is about property *names*, so folding the
     * two together would reject every list-valued property.
     *
     * @param array<array-key, mixed> $properties
     */
    private function rejectUnsupportedPropertyValues(array $properties): void
    {
        foreach ($properties as $key => $value) {
            $this->rejectUnsupportedPropertyValue((string) $key, $value);
        }
    }

    /**
     * The permitted domain is scalar, null, an array recursively of the same domain, {@see Struct},
     * `\DateTimeInterface` and `\BackedEnum`; objects are matched by `instanceof`, so a subclass of any of
     * them is admitted too. The two non-`Struct` object types are in because the bar is concealment rather
     * than objecthood: neither can hold a `Struct` in its object graph, and both already reach the wire
     * unchanged, since {@see StructEncoder::encode()} recurses only into a value that is itself a `Struct`
     * or an array of them and passes every other object through raw.
     *
     * The key names the top-level property the value hangs under, at whatever depth the offender sits.
     */
    private function rejectUnsupportedPropertyValue(string $key, mixed $value): void
    {
        if ($value === null || \is_scalar($value)) {
            return;
        }

        if (\is_array($value)) {
            foreach ($value as $nested) {
                $this->rejectUnsupportedPropertyValue($key, $nested);
            }

            return;
        }

        if ($value instanceof Struct || $value instanceof \DateTimeInterface || $value instanceof \BackedEnum) {
            return;
        }

        throw ContentSystemException::unsupportedPropertyValueType($key, get_debug_type($value));
    }

    /**
     * PHP casts a numeric-string array key to an integer, so rejecting integer keys rejects both `12` and
     * `'12'`. Slot names are names; none of them may be numeric. The map is typed wider than the declared
     * shape on purpose — the whole point is to catch the caller that ignores the declaration.
     *
     * @param array<array-key, mixed> $slots
     */
    private function rejectMalformedSlots(array $slots): void
    {
        foreach ($slots as $name => $children) {
            if (\is_int($name)) {
                throw ContentSystemException::invalidMapKey('Rendered element slot map', 'int');
            }

            if (!\is_array($children) || !array_is_list($children)) {
                throw ContentSystemException::invalidMapValue(
                    'Rendered element slot map',
                    $name,
                    'list',
                    get_debug_type($children)
                );
            }

            foreach ($children as $child) {
                if ($child instanceof self) {
                    continue;
                }

                throw ContentSystemException::invalidMapValue(
                    'Rendered element slot child list',
                    $name,
                    self::class,
                    get_debug_type($child)
                );
            }
        }
    }
}
