<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;

/**
 * One authored element on the storage side: what the admin edits, what validation and mutation operate on,
 * and what the storage column holds. Immutable — every edit produces a new instance through a `with*()`
 * method, so any instance can be held or reused without defensive copying.
 *
 * Property values are {@see StoredValue}s rather than raw PHP values, which is what keeps a hydrated
 * entity out of the stored tree by type rather than by convention. The render-time counterpart carries
 * raw values, a flat property map and no wiring at all.
 */
#[Package('framework')]
final readonly class StoredElement implements \JsonSerializable
{
    /**
     * @param array<string, DataRequirement> $dataRequirements
     * @param array<string, StoredValue> $properties
     * @param array<string, list<StoredElement>> $slots
     * @param array<string, string> $attributedSpecifications
     */
    public function __construct(
        public string $id,
        public string $component,
        public array $dataRequirements = [],
        private array $properties = [],
        public array $slots = [],
        public ContextDefinitions $contextDefinitions = new ContextDefinitions(),
        public ElementStyle $style = new ElementStyle(),
        public array $attributedSpecifications = [],
    ) {
        $this->rejectNumericKeys($properties, 'Element property map');
        $this->rejectNumericKeys($dataRequirements, 'Element data requirement map');
        $this->rejectNumericKeys($slots, 'Element slot map');
    }

    /**
     * @return array<string, StoredValue>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    /**
     * `null` means the key is absent. An authored null is a present value whose variant is null, so it comes
     * back as a {@see StoredValue} answering true to `isNull()`.
     */
    public function property(string $key): ?StoredValue
    {
        return $this->properties[$key] ?? null;
    }

    /**
     * The id value domain — not the reserved root literal, not a string PHP casts to an integer — is
     * enforced at {@see StoredElementCodec::decode()} only; this method re-checks nothing. Every current
     * caller mints a random hex id, which satisfies the domain by construction. A caller carrying
     * authored input must route it through the decode gate instead of this method.
     */
    public function withId(string $id): self
    {
        return $this->copy(id: $id);
    }

    public function withComponent(string $component): self
    {
        return $this->copy(component: $component);
    }

    /**
     * @param array<string, DataRequirement> $dataRequirements
     */
    public function withDataRequirements(array $dataRequirements): self
    {
        return $this->copy(dataRequirements: $dataRequirements);
    }

    /**
     * @param array<string, StoredValue> $properties
     */
    public function withProperties(array $properties): self
    {
        return $this->copy(properties: $properties);
    }

    /**
     * @param array<string, list<StoredElement>> $slots
     */
    public function withSlots(array $slots): self
    {
        return $this->copy(slots: $slots);
    }

    public function withContextDefinitions(ContextDefinitions $contextDefinitions): self
    {
        return $this->copy(contextDefinitions: $contextDefinitions);
    }

    public function withStyle(ElementStyle $style): self
    {
        return $this->copy(style: $style);
    }

    /**
     * @param array<string, string> $attributedSpecifications
     */
    public function withAttributedSpecifications(array $attributedSpecifications): self
    {
        return $this->copy(attributedSpecifications: $attributedSpecifications);
    }

    /**
     * The canonical storage wire shape, shared by the storage column, the admin entity read, the mutation
     * responses and the preview request bodies — one audience, one shape, produced in a single walk.
     *
     * `id`, `component` and `properties` are always present; everything else is omitted when empty, so a
     * bare element stays a three-key object. Attribution rides inline and follows the same rule, matching
     * the admin schema, which states it is omitted rather than serialized as an empty object.
     *
     * Every empty map is emitted as `[]`. PHP cannot carry an empty map as `{}` through a serializer the
     * write path shares without breaking the array-typed DAL write, so `[]` is the one canonical shape.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'component' => $this->component,
            'properties' => array_map(
                static fn (StoredValue $value): mixed => $value->jsonSerialize(),
                $this->properties
            ),
        ];

        if ($this->dataRequirements !== []) {
            $data['dataRequirements'] = array_map(
                static fn (DataRequirement $requirement): array => $requirement->jsonSerialize(),
                $this->dataRequirements
            );
        }

        if ($this->slots !== []) {
            $slots = [];
            foreach ($this->slots as $slotName => $children) {
                $slots[$slotName] = array_map(
                    static fn (self $child): array => $child->jsonSerialize(),
                    $children
                );
            }
            $data['slots'] = $slots;
        }

        $providers = $this->contextDefinitions->getAllProviders();
        if ($providers !== []) {
            $data['providesContext'] = array_map(
                static fn (ContextProvider $provider): array => $provider->jsonSerialize(),
                $providers
            );
        }

        $consumers = $this->contextDefinitions->getAllConsumers();
        if ($consumers !== []) {
            $data['acceptsContext'] = array_map(
                static fn (ContextConsumer $consumer): array => $consumer->jsonSerialize(),
                $consumers
            );
        }

        if (!$this->style->isEmpty()) {
            $data['style'] = $this->style->toArray();
        }

        if ($this->attributedSpecifications !== []) {
            $data['attributedSpecifications'] = $this->attributedSpecifications;
        }

        return $data;
    }

    /**
     * The single place that enumerates every field. Each `with*()` overrides exactly one argument and lets
     * the rest fall through, so adding a field means touching this method and its own accessor pair only.
     * A `null` argument here means "not overridden": no field is nullable, so the sentinel is unambiguous.
     *
     * @param array<string, DataRequirement>|null $dataRequirements
     * @param array<string, StoredValue>|null $properties
     * @param array<string, list<StoredElement>>|null $slots
     * @param array<string, string>|null $attributedSpecifications
     */
    private function copy(
        ?string $id = null,
        ?string $component = null,
        ?array $dataRequirements = null,
        ?array $properties = null,
        ?array $slots = null,
        ?ContextDefinitions $contextDefinitions = null,
        ?ElementStyle $style = null,
        ?array $attributedSpecifications = null,
    ): self {
        return new self(
            $id ?? $this->id,
            $component ?? $this->component,
            $dataRequirements ?? $this->dataRequirements,
            $properties ?? $this->properties,
            $slots ?? $this->slots,
            $contextDefinitions ?? $this->contextDefinitions,
            $style ?? $this->style,
            $attributedSpecifications ?? $this->attributedSpecifications,
        );
    }

    /**
     * PHP casts a numeric-string array key to an integer, so rejecting integer keys rejects both `12` and
     * `'12'`. Wiring keys name properties, data requirements and slots, and none of those may be numeric.
     *
     * @param array<array-key, mixed> $map
     */
    private function rejectNumericKeys(array $map, string $mapType): void
    {
        foreach (array_keys($map) as $key) {
            if (\is_int($key)) {
                throw ContentSystemException::invalidMapKey($mapType, 'int');
            }
        }
    }
}
