<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutPresetPayloadCompiler
{
    public function __construct(
        private readonly DraftLayoutDecoder $decoder,
        private readonly StoredElementCodec $codec,
        private readonly AbstractContentSystemElementTypeRegistry $elementTypes,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $layout the preset's `layout:` shorthand nodes
     *
     * @return list<array<string, mixed>> the encoded element payload
     */
    public function compile(array $layout): array
    {
        $draft = array_map($this->compileNode(...), $layout);

        $decoded = $this->decoder->decode($draft);

        return array_map(fn ($element): array => $this->codec->encode($element), $decoded);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function compileNode(array $node): array
    {
        $node['id'] = Uuid::randomHex();
        $node = $this->withStoredShapeProperties($node);

        if (\is_array($node['slots'] ?? null)) {
            $node['slots'] = array_map(
                fn (mixed $children): mixed => \is_array($children) ? array_map($this->compileNode(...), $children) : $children,
                $node['slots'],
            );
        }

        return $node;
    }

    /**
     * A preset can only name the system language, so its author writes a translatable property as a plain value
     * and the stored shape {@see PropertyType::inStoredShape()} states is applied here. An authored language map
     * is wrapped again and then fails the write-time conformance check, which is intended. A component the
     * registry does not know cannot answer which keys are translatable, so its properties stay verbatim, and a
     * malformed `component` or `properties` is left for the decoder to reject.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function withStoredShapeProperties(array $node): array
    {
        $component = $node['component'] ?? null;
        $properties = $node['properties'] ?? null;

        if (!\is_string($component) || !\is_array($properties) || !$this->elementTypes->has($component)) {
            return $node;
        }

        foreach ($this->elementTypes->get($component)->properties() as $key => $property) {
            if (!$property->type()->translatable() || !\array_key_exists($key, $properties)) {
                continue;
            }

            $properties[$key] = $property->type()->inStoredShape($properties[$key]);
        }

        $node['properties'] = $properties;

        return $node;
    }
}
