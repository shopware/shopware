<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\Log\Package;

/**
 * The codec fixture the four groups of the split share: one codec, the base wire shape every override starts
 * from, and the two deep-nesting builders whose accepted and rejected depths are asserted in different files.
 *
 * @internal
 */
#[Package('framework')]
abstract class StoredElementCodecTestCase extends TestCase
{
    /**
     * The provider is stubbed down to routing; the `entity` source's real config serializer does the decoding,
     * so every `config` in these files' fixtures has to be a shape production could actually store.
     */
    protected function codec(): StoredElementCodec
    {
        $serializer = new EntityLoaderConfigSerializer();

        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configProvider->method('decode')->willReturnCallback(
            /**
             * @param array<string, mixed> $data
             */
            static fn (string $source, array $data): AbstractContentDataLoaderConfig => $serializer->decode($data)
        );

        return new StoredElementCodec($configProvider);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function baseWire(array $overrides): array
    {
        return array_merge([
            'id' => 'el-1',
            'component' => 'core:text',
            'properties' => [],
        ], $overrides);
    }

    /**
     * A chain of `$count` elements, each the single child of the one above it, so the deepest sits `$count - 1`
     * levels below the root.
     *
     * @return array<string, mixed>
     */
    protected static function nestedElements(int $count): array
    {
        $element = ['id' => 'el-' . ($count - 1), 'component' => 'core:text', 'properties' => []];

        for ($level = $count - 2; $level >= 0; --$level) {
            $element = [
                'id' => 'el-' . $level,
                'component' => 'core:section',
                'properties' => [],
                'slots' => ['main' => [$element]],
            ];
        }

        return $element;
    }

    /**
     * One element whose single property holds `$count` nested single-entry lists, so the innermost list sits
     * `$count - 1` levels below the property payload.
     *
     * @return array<string, mixed>
     */
    protected static function elementWithNestedValue(int $count): array
    {
        $value = 'leaf';

        for ($level = 0; $level < $count; ++$level) {
            $value = [$value];
        }

        return ['id' => 'el-1', 'component' => 'core:text', 'properties' => ['deep' => $value]];
    }
}
