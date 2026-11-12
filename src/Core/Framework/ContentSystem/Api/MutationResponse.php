<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * The wire response shared by all draft and persisted mutation routes.
 *
 * Output-only: serialized to JSON and discarded — never cached, stored in a DAL SerializedField, or passed to
 * StructNormalizer::denormalize(). jsonSerialize() casts the map-typed fields (resolutions/droppedProperties)
 * to {} when empty; the element tree's own maps stay [] (the shape every other read path emits). Safe only on
 * this path; a future requirement that caches or reconstructs this object must revisit it.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class MutationResponse implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $layout serialized element tree
     * @param array<string, list<array<string, mixed>>> $resolutions per-element resolutions
     * @param array<string, mixed> $diagnostics normalized diagnostics report
     * @param list<string> $affectedElementIds
     * @param list<array<string, mixed>> $orphaned serialized detached subtrees
     * @param list<string> $droppedWiring
     * @param array<string, mixed> $droppedProperties dropped property values
     */
    private function __construct(
        public array $layout,
        public array $resolutions,
        public array $diagnostics,
        public array $affectedElementIds,
        public array $orphaned,
        public array $droppedWiring,
        public array $droppedProperties,
    ) {
    }

    /**
     * Elements go out through the codec, the same encode the storage column uses, so the response and the stored
     * shape cannot drift. Dropped property values are unwrapped out of their storage envelope here, at the wire
     * boundary: the wire contract carries the raw authored value, unchanged by the storage model's arrival.
     */
    public static function fromResult(MutationResult $result, StoredElementCodec $elementCodec): self
    {
        $normalizer = new LayoutDiagnosticsResultNormalizer();

        return new self(
            array_map($elementCodec->encode(...), $result->layout->roots),
            $normalizer->normalizeResolutions($result->resolutions),
            $normalizer->normalizeReport($result->diagnostics),
            $result->affectedElementIds,
            array_map($elementCodec->encode(...), $result->orphaned),
            $result->droppedWiring,
            array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $result->droppedProperties),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'layout' => $this->layout,
            'resolutions' => (object) $this->resolutions,
            'diagnostics' => $this->diagnostics,
            'affectedElementIds' => $this->affectedElementIds,
            'orphaned' => $this->orphaned,
            'droppedWiring' => $this->droppedWiring,
            'droppedProperties' => (object) $this->droppedProperties,
        ];
    }
}
