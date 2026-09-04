<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
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
    ) {
    }

    /**
     * @param list<mixed> $layout the preset's `layout:` shorthand nodes
     *
     * @return list<array<string, mixed>> the encoded element payload
     */
    public function compile(array $layout): array
    {
        $draft = array_map(fn (mixed $node): array => $this->toDraftElement($node), $layout);

        $decoded = $this->decoder->decode($draft);

        return array_map(fn ($element): array => $this->codec->encode($element), $decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function toDraftElement(mixed $node): array
    {
        if (!\is_array($node)) {
            throw ContentSystemException::layoutPresetInvalidLayout('Each layout node must be a mapping with a "component".');
        }

        $component = $node['component'] ?? null;
        if (!\is_string($component) || $component === '') {
            throw ContentSystemException::layoutPresetInvalidLayout('Each layout node requires a non-empty "component".');
        }

        $properties = $node['properties'] ?? [];
        if (!\is_array($properties)) {
            throw ContentSystemException::layoutPresetInvalidLayout(\sprintf('The "properties" of "%s" must be a mapping.', $component));
        }

        $element = [
            'id' => Uuid::randomHex(),
            'component' => $component,
            'properties' => $properties,
        ];

        $slots = $this->compileSlots($node['slots'] ?? null, $component);
        if ($slots !== []) {
            $element['slots'] = $slots;
        }

        return $element;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function compileSlots(mixed $slots, string $type): array
    {
        if ($slots === null) {
            return [];
        }

        if (!\is_array($slots)) {
            throw ContentSystemException::layoutPresetInvalidLayout(\sprintf('The "slots" of "%s" must map a slot name to a list of child nodes.', $type));
        }

        $compiled = [];

        foreach ($slots as $slotName => $children) {
            if (!\is_string($slotName) || !\is_array($children)) {
                throw ContentSystemException::layoutPresetInvalidLayout(\sprintf('The "slots" of "%s" must map a slot name to a list of child nodes.', $type));
            }

            $compiled[$slotName] = array_map(fn (mixed $child): array => $this->toDraftElement($child), array_values($children));
        }

        return $compiled;
    }
}
