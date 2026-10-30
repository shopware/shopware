<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
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
     * @param list<array<string, mixed>> $layout the preset's `layout:` shorthand nodes
     * @param array<string, LayoutPresetSpecificationDto> $presets the presets a `preset:` reference resolves against
     * @param list<string> $path the ids of the presets being expanded, starting with the compiled one
     *
     * @return list<array<string, mixed>> the encoded element payload
     */
    public function compile(array $layout, array $presets = [], array $path = []): array
    {
        $draft = array_map($this->mintIds(...), $this->resolveNestedPresets($layout, $presets, $path));

        $decoded = $this->decoder->decode($draft);

        return array_map(fn ($element): array => $this->codec->encode($element), $decoded);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function mintIds(array $node): array
    {
        $node['id'] = Uuid::randomHex();

        if (\is_array($node['slots'] ?? null)) {
            $node['slots'] = array_map(
                fn (mixed $children): mixed => \is_array($children) ? array_map($this->mintIds(...), $children) : $children,
                $node['slots'],
            );
        }

        return $node;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, LayoutPresetSpecificationDto> $presets
     * @param list<string> $path
     *
     * @return list<array<string, mixed>>
     */
    private function resolveNestedPresets(array $nodes, array $presets, array $path): array
    {
        $resolved = [];

        foreach ($nodes as $node) {
            $preset = $node['preset'] ?? null;

            if (\is_string($preset)) {
                if (!\array_key_exists($preset, $presets)) {
                    throw ContentSystemException::layoutPresetUnknownReference($preset, $path);
                }

                if (\in_array($preset, $path, true)) {
                    throw ContentSystemException::layoutPresetCircularReference([...$path, $preset]);
                }

                array_push($resolved, ...$this->resolveNestedPresets($presets[$preset]->layout, $presets, [...$path, $preset]));

                continue;
            }

            if (\is_array($node['slots'] ?? null)) {
                foreach ($node['slots'] as $slot => $children) {
                    if (\is_array($children) && array_is_list($children)) {
                        $node['slots'][$slot] = $this->resolveNestedPresets($children, $presets, $path);
                    }
                }
            }

            $resolved[] = $node;
        }

        return $resolved;
    }
}
