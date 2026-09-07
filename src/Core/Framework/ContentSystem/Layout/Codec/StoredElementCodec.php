<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * Both directions of one wire shape: the canonical storage array of a single {@see StoredElement} and the
 * element itself. Decode and encode live together so they cannot drift apart.
 *
 * Encode is a straight delegation to {@see StoredElement::jsonSerialize()}, which already owns that canonical
 * shape; this class adds no second definition of it.
 *
 * Decode is the sanctioned mint site for a {@see StoredElement}: every raw value reaches the stored model
 * through {@see StoredValue::fromDecoded()} or a typed {@see StoredValue} constructor, and an unknown key is
 * a decode failure rather than a silently dropped field — at the top level, inside a data requirement entry
 * and inside a consumer entry alike, matching the three key sets {@see StoredTreeConstraints} closes on the
 * write path. Numeric wiring keys are not checked here — the element constructor rejects them, and decode
 * leaves that throw untouched.
 *
 * Both context-wiring maps are decoded by {@see StoredElementWiringDecoder}, which also owns the consumer entry's
 * closed key set, the provider entry's deliberately open one, and the two ordered tiers of wiring rules
 * decode judges. This class delegates all three of its entry points and adds nothing to them; the element-local
 * tier runs from {@see decodeElement()} once both maps are complete.
 *
 * A malformed structural container is invalid storage and fails decode outright: `dataRequirements`, `slots`,
 * `providesContext`, `acceptsContext`, `style` and `attributedSpecifications` all throw when present as a
 * non-null value that is not an array, because a container whose shape is wrong cannot be interpreted. An
 * explicit `null` for one of these keys is treated the same as an absent key and falls back to an empty
 * container — the same null-as-absent treatment {@see decodeConsumers()} already gives `redistribute`,
 * `consumerAlias` and `propertyAlias`, and {@see decodeDataRequirements()} already gives `key`: a null
 * container carries no information a caller could act on differently from an absent one.
 *
 * This class is lenient in two places, each narrow and independently justified rather than a general tolerance
 * for malformed input. {@see decodeStyle()} is structurally strict — a malformed option name, value or breakpoint
 * throws like any other malformed container — but stays registry-blind: an option name the registry no longer
 * knows is a well-formed string and still reads verbatim, so removing a style option provider does not make an
 * already-stored layout unreadable; the registry-aware check belongs to the write boundary, not here. The
 * provider entry's open key set, described on {@see StoredElementWiringDecoder}, is the other leniency. Every other
 * malformed value throws.
 *
 * @internal
 */
#[Package('framework')]
final class StoredElementCodec
{
    /**
     * The deepest nesting decode admits, counted from the element handed to {@see decode()} at level zero.
     * It bounds both kinds of nesting the wire shape carries — elements below elements through slots, and
     * arrays inside a single property payload — and is enforced on entry to each recursive step, so a tree
     * or a property value that exceeds it fails before the recursion can run out of stack.
     */
    public const MAX_NESTING_DEPTH = 50;

    /**
     * @var list<string>
     */
    private const ELEMENT_KEYS = [
        'id',
        'component',
        'properties',
        'dataRequirements',
        'slots',
        'providesContext',
        'acceptsContext',
        'style',
        'attributedSpecifications',
    ];

    /**
     * @var list<string>
     */
    private const DATA_REQUIREMENT_KEYS = [
        'key',
        'source',
        'config',
    ];

    private readonly StoredElementWiringDecoder $wiring;

    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configProvider,
    ) {
        $this->wiring = new StoredElementWiringDecoder();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function decode(array $data): StoredElement
    {
        return $this->decodeElement($data, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function encode(StoredElement $element): array
    {
        return $element->jsonSerialize();
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function decodeElement(array $data, int $depth): StoredElement
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw ContentSystemException::invalidFieldValueType(
                'slots',
                \sprintf('element nesting at most %d levels deep', self::MAX_NESTING_DEPTH),
                'deeper nesting'
            );
        }

        $this->rejectUnknownKeys($data, self::ELEMENT_KEYS, 'element', 'element');

        $id = $data['id'] ?? null;
        if (!\is_string($id)) {
            throw ContentSystemException::invalidFieldValueType('id', 'string', get_debug_type($id));
        }

        $this->rejectReservedOrCastableId($id);

        $component = $data['component'] ?? null;
        if (!\is_string($component)) {
            throw ContentSystemException::invalidFieldValueType('component', 'string', get_debug_type($component));
        }

        try {
            $dataRequirements = $this->decodeDataRequirements($data['dataRequirements'] ?? []);
        } catch (ContentSystemException $exception) {
            throw $this->withElementId($exception, $id);
        }

        $properties = $this->decodeProperties($data['properties'] ?? []);
        $slots = $this->decodeSlots($data['slots'] ?? [], $depth);
        $providers = $this->wiring->decodeProviders($data['providesContext'] ?? []);
        $consumers = $this->wiring->decodeConsumers($data['acceptsContext'] ?? []);
        $style = $this->decodeStyle($data['style'] ?? []);
        $attributedSpecifications = $this->decodeAttributedSpecifications($data['attributedSpecifications'] ?? []);

        $this->wiring->rejectInvalidElementWiring($consumers, $providers);

        return new StoredElement(
            $id,
            $component,
            $dataRequirements,
            $properties,
            $slots,
            new ContextDefinitions($providers, $consumers),
            $style,
            $attributedSpecifications,
        );
    }

    /**
     * The two id values the rest of the module cannot carry. {@see VirtualRootWrapper::VIRTUAL_ROOT_ID} is
     * minted by the wrap step, so an authored element holding it collides on every wrapping render; and
     * {@see ResolvedValueIndexFactory} keys its assignments map by element id, so an id PHP casts to an
     * integer array key puts an integer into a map declared string-keyed — which encodes as a JSON list once
     * those keys run 0..n-1, and as integer-looking members otherwise. The castability test puts the id through PHP's
     * own array-key cast rather than restating the rule the codec's map-key rejections rely on.
     *
     * This is the shared admission point: the DAL write path reaches it through
     * {@see StoredElementListFieldSerializer}, every draft route through {@see DraftLayoutDecoder}.
     */
    private function rejectReservedOrCastableId(string $id): void
    {
        if ($id === VirtualRootWrapper::VIRTUAL_ROOT_ID) {
            throw ContentSystemException::invalidElementId($id, 'it is the reserved virtual-root id');
        }

        if (!\is_string(array_key_first([$id => null]))) {
            throw ContentSystemException::invalidElementId($id, 'PHP casts it to an integer array key');
        }
    }

    /**
     * Re-throws a CONFIG_SERIALIZER_NOT_REGISTERED fault carrying the element whose stored dataRequirements
     * named the unregistered source, so the caller can see which element to fix. The caught exception's own
     * "source" parameter is read back rather than re-derived. Every other ContentSystemException is returned
     * unchanged.
     */
    private function withElementId(ContentSystemException $exception, string $elementId): ContentSystemException
    {
        if ($exception->getErrorCode() !== ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED) {
            return $exception;
        }

        $source = $exception->getParameter('source');
        if (!\is_string($source)) {
            return $exception;
        }

        return ContentSystemException::configSerializerNotRegistered($source, $elementId);
    }

    /**
     * A numeric property key survives the cast below as an integer key, because PHP maps a numeric string
     * back to an integer on assignment. That is deliberate: the element constructor is the one place that
     * rejects a numeric wiring key, and it needs the key to still be numeric when it gets there.
     *
     * @return array<string, StoredValue>
     */
    private function decodeProperties(mixed $raw): array
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('properties', 'array', get_debug_type($raw));
        }

        $properties = [];
        foreach ($raw as $key => $value) {
            $properties[(string) $key] = $this->decodeValue($value, \sprintf('properties[%s]', $key), 0);
        }

        return $properties;
    }

    /**
     * Wraps one raw property payload, bounding its own nesting on the way down. {@see StoredValue::fromDecoded()}
     * recurses without a bound of its own, so only the scalar and null leaves are handed to it and every array
     * level is walked here instead.
     */
    private function decodeValue(mixed $value, string $path, int $depth): StoredValue
    {
        if (!\is_array($value)) {
            return StoredValue::fromDecoded($value);
        }

        if ($depth > self::MAX_NESTING_DEPTH) {
            throw ContentSystemException::invalidFieldValueType(
                $path,
                \sprintf('value nesting at most %d levels deep', self::MAX_NESTING_DEPTH),
                'deeper nesting'
            );
        }

        $items = [];
        foreach ($value as $key => $item) {
            $items[$key] = $this->decodeValue($item, \sprintf('%s[%s]', $path, $key), $depth + 1);
        }

        if (array_is_list($value)) {
            return StoredValue::ofList(array_values($items));
        }

        return StoredValue::ofMap($items);
    }

    /**
     * @return array<string, DataRequirement>
     */
    private function decodeDataRequirements(mixed $raw): array
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('dataRequirements', 'array', get_debug_type($raw));
        }

        $requirements = [];
        foreach ($raw as $mapKey => $requirement) {
            $path = \sprintf('dataRequirements[%s]', $mapKey);

            if (!\is_array($requirement)) {
                throw ContentSystemException::invalidFieldValueType($path, 'array', get_debug_type($requirement));
            }

            $this->rejectUnknownKeys($requirement, self::DATA_REQUIREMENT_KEYS, $path, 'data requirement');

            $source = $requirement['source'] ?? null;
            if (!\is_string($source)) {
                throw ContentSystemException::invalidFieldValueType($path . '.source', 'string', get_debug_type($source));
            }

            // An absent inner key falls back to the map key it sits under. A numeric map key is stringified
            // only so the requirement can be built at all; the element constructor still sees the numeric map
            // key and rejects it.
            $key = $requirement['key'] ?? (string) $mapKey;
            if (!\is_string($key)) {
                throw ContentSystemException::invalidFieldValueType($path . '.key', 'string', get_debug_type($key));
            }

            $config = $requirement['config'] ?? [];
            if (!\is_array($config)) {
                throw ContentSystemException::invalidFieldValueType($path . '.config', 'array', get_debug_type($config));
            }

            $requirements[(string) $mapKey] = new DataRequirement(
                $key,
                $source,
                $this->configProvider->decode($source, $this->stringKeyed($config, $path . '.config'))
            );
        }

        return $requirements;
    }

    /**
     * @return array<string, list<StoredElement>>
     */
    private function decodeSlots(mixed $raw, int $depth): array
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('slots', 'array', get_debug_type($raw));
        }

        $slots = [];
        foreach ($raw as $slotName => $children) {
            $path = \sprintf('slots[%s]', $slotName);

            if (!\is_array($children) || !array_is_list($children)) {
                throw ContentSystemException::invalidFieldValueType($path, 'list of elements', get_debug_type($children));
            }

            $decoded = [];
            foreach ($children as $index => $child) {
                if (!\is_array($child)) {
                    throw ContentSystemException::invalidFieldValueType(
                        \sprintf('%s[%d]', $path, $index),
                        'array',
                        get_debug_type($child)
                    );
                }

                $decoded[] = $this->decodeElement($child, $depth + 1);
            }

            $slots[(string) $slotName] = $decoded;
        }

        return $slots;
    }

    /**
     * Registry-free structural decode: an option name must be a string, a scalar value is kept verbatim as a
     * flat option, and an array value must be a non-empty map keyed by declared breakpoints with scalar
     * values. Every malformed shape throws rather than being dropped — the write path decodes before the
     * constraint pass judges the tree, so silently cleaning here would let a write with malformed style
     * succeed with the style stripped.
     *
     * Strictness here is structural, not registry-driven: an option name the registry no longer knows is a
     * well-formed string and still decodes, so removing a provider does not make a stored layout undecodable.
     */
    private function decodeStyle(mixed $raw): ElementStyle
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('style', 'array', get_debug_type($raw));
        }

        $breakpoints = Breakpoint::values();

        $clean = [];
        foreach ($raw as $optionName => $value) {
            if (!\is_string($optionName)) {
                throw ContentSystemException::invalidMapKey('Element style map', get_debug_type($optionName));
            }

            if (\is_scalar($value)) {
                $clean[$optionName] = $value;

                continue;
            }

            if (!\is_array($value)) {
                throw ContentSystemException::invalidFieldValueType(
                    \sprintf('style[%s]', $optionName),
                    'scalar or breakpoint map',
                    get_debug_type($value)
                );
            }

            if ($value === []) {
                throw ContentSystemException::invalidFieldValueType(
                    \sprintf('style[%s]', $optionName),
                    'a breakpoint map holding at least one breakpoint',
                    'empty map'
                );
            }

            $cleanMap = [];
            foreach ($value as $breakpoint => $breakpointValue) {
                if (!\in_array($breakpoint, $breakpoints, true)) {
                    throw ContentSystemException::unknownStyleBreakpoint(
                        $optionName,
                        \is_string($breakpoint) ? $breakpoint : get_debug_type($breakpoint),
                        $breakpoints
                    );
                }

                if (!\is_scalar($breakpointValue)) {
                    throw ContentSystemException::invalidFieldValueType(
                        \sprintf('style[%s][%s]', $optionName, $breakpoint),
                        'scalar',
                        get_debug_type($breakpointValue)
                    );
                }

                $cleanMap[$breakpoint] = $breakpointValue;
            }

            $clean[$optionName] = $cleanMap;
        }

        return new ElementStyle($clean);
    }

    /**
     * @return array<string, string>
     */
    private function decodeAttributedSpecifications(mixed $raw): array
    {
        if (!\is_array($raw)) {
            throw ContentSystemException::invalidFieldValueType('attributedSpecifications', 'array', get_debug_type($raw));
        }

        $attributed = [];
        foreach ($raw as $key => $specificationId) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey('Element attributed specification map', get_debug_type($key));
            }

            if (!\is_string($specificationId)) {
                throw ContentSystemException::invalidFieldValueType(
                    \sprintf('attributedSpecifications[%s]', $key),
                    'string',
                    get_debug_type($specificationId)
                );
            }

            $attributed[$key] = $specificationId;
        }

        return $attributed;
    }

    /**
     * A key set decode closes: a key outside it is a decode failure, so a field the shape does not carry is
     * never silently dropped on the way to the stored model. The two sets closed here are the element's own
     * and the data requirement entry's; {@see StoredElementWiringDecoder} closes the third, the consumer entry's.
     * The write-path descriptor closes the same three, so neither side admits what the other refuses.
     *
     * @param array<array-key, mixed> $data
     * @param list<string> $known
     */
    private function rejectUnknownKeys(array $data, array $known, string $path, string $subject): void
    {
        foreach (array_keys($data) as $key) {
            if (\in_array($key, $known, true)) {
                continue;
            }

            throw ContentSystemException::invalidFieldValueType(
                $path,
                \sprintf('only known %s keys', $subject),
                \sprintf('unknown key "%s"', $key)
            );
        }
    }

    /**
     * A JSON object with a numeric member name decodes to an integer array key, which the data-requirement
     * config factory cannot take. Reject it here rather than stringifying it back.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $map, string $path): array
    {
        $stringKeyed = [];
        foreach ($map as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey($path, get_debug_type($key));
            }

            $stringKeyed[$key] = $value;
        }

        return $stringKeyed;
    }
}
