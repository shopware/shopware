<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
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

        $style = $node['style'] ?? null;
        if ($style !== null) {
            if (!\is_array($style)) {
                throw ContentSystemException::layoutPresetInvalidLayout(\sprintf('The "style" of "%s" must be a mapping.', $component));
            }

            $element['style'] = $this->normalizeStyle($style, $component);
        }

        $slots = $this->compileSlots($node['slots'] ?? null, $component);
        if ($slots !== []) {
            $element['slots'] = $slots;
        }

        return $element;
    }

    /**
     * @param array<int|string, mixed> $style
     *
     * @return array<string, mixed>
     */
    private function normalizeStyle(array $style, string $component): array
    {
        $allowed = Breakpoint::values();
        $normalized = [];

        foreach ($style as $option => $value) {
            if (!\is_array($value)) {
                $normalized[(string) $option] = array_fill_keys($allowed, $value);

                continue;
            }

            $keys = array_keys($value);

            $unknown = array_diff($keys, $allowed);
            if ($unknown !== []) {
                throw ContentSystemException::layoutPresetInvalidLayout(\sprintf(
                    'Unknown breakpoint(s) "%s" in style option "%s" of "%s". Allowed breakpoints: %s.',
                    implode(', ', $unknown),
                    (string) $option,
                    $component,
                    implode(', ', $allowed),
                ));
            }

            $missing = array_diff($allowed, $keys);
            if ($missing !== []) {
                throw ContentSystemException::layoutPresetInvalidLayout(\sprintf(
                    'Style option "%s" of "%s" is missing breakpoint(s) "%s". A breakpoint mapping must define all: %s.',
                    (string) $option,
                    $component,
                    implode(', ', $missing),
                    implode(', ', $allowed),
                ));
            }

            $normalized[(string) $option] = $value;
        }

        return $normalized;
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
