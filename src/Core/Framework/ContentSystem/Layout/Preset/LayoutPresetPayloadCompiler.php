<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Preset;

use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
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
     * @param list<array<string, mixed>> $layout the preset's `layout:` shorthand nodes
     *
     * @return list<array<string, mixed>> the encoded element payload
     */
    public function compile(array $layout): array
    {
        $draft = array_map($this->mintIds(...), $layout);

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
}
