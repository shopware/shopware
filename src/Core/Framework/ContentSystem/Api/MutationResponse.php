<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * The wire response shared by the draft and persisted layout mutation routes, and the single definition of their
 * response shape and of which fields encode as JSON maps versus lists.
 *
 * Output-only: this object is serialized to JSON for the HTTP response and discarded. It is never cached, never
 * stored in a DAL SerializedField, never sent over the message bus, and never passed to StructNormalizer::denormalize().
 * jsonSerialize() casts the response-level resolutions/droppedProperties/applicableBindings maps to {} when empty;
 * the element tree carries empty element maps as [] (the same shape every other read path emits), and each
 * applicableBindings entry is a list<string>, [] when empty. It is safe only on that path; a future requirement
 * that caches or reconstructs this object must revisit it.
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
     * @param array<string, list<string>> $applicableBindings applicable binding specification ids, keyed by element id
     */
    private function __construct(
        public array $layout,
        public array $resolutions,
        public array $diagnostics,
        public array $affectedElementIds,
        public array $orphaned,
        public array $droppedWiring,
        public array $droppedProperties,
        public array $applicableBindings,
    ) {
    }

    public static function fromResult(MutationResult $result, ContentElementFieldSerializer $elementSerializer): self
    {
        $normalizer = new LayoutDiagnosticsResultNormalizer();

        return new self(
            array_map($elementSerializer->serializeContentElement(...), $result->layout),
            $normalizer->normalizeResolutions($result->resolutions),
            $normalizer->normalizeReport($result->diagnostics),
            $result->affectedElementIds,
            array_map($elementSerializer->serializeContentElement(...), $result->orphaned),
            $result->droppedWiring,
            $result->droppedProperties,
            $result->applicableBindings,
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
            'applicableBindings' => (object) $this->applicableBindings,
        ];
    }
}
