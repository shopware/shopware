<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Routing of a decoded data requirement entry to its source's config serializer.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementCodec::class)]
class StoredElementCodecDataRequirementTest extends StoredElementCodecTestCase
{
    /**
     * @param array<string, mixed> $requirement
     */
    #[DataProvider('dataRequirementKeyProvider')]
    #[TestDox('resolves the data requirement key to $_dataName')]
    public function testDecodeResolvesTheDataRequirementKey(array $requirement, string $expected): void
    {
        $element = $this->codec()->decode(self::baseWire(['dataRequirements' => ['products' => $requirement]]));

        // The map key always stays the outer one; only the requirement's own key falls back to it.
        static::assertSame(['products'], array_keys($element->dataRequirements));
        static::assertSame($expected, $element->dataRequirements['products']->key);
    }

    #[TestDox('names the element whose data requirement points at an unregistered config serializer source')]
    public function testDecodeThrowsWithElementIdWhenSourceUnregistered(): void
    {
        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator([]));
        $codec = new StoredElementCodec($provider);

        $wire = self::baseWire([
            'id' => 'el-unregistered',
            'dataRequirements' => [
                'products' => ['source' => 'removed_plugin_source', 'config' => []],
            ],
        ]);

        $expected = ContentSystemException::configSerializerNotRegistered('removed_plugin_source', 'el-unregistered');

        $this->expectExceptionObject($expected);

        $codec->decode($wire);
    }

    #[TestDox('propagates an unrelated ContentSystemException from a data requirement unchanged')]
    public function testDecodePropagatesUnrelatedContentSystemExceptionFromDataRequirements(): void
    {
        $internalFault = ContentSystemException::invalidFieldType(AbstractContentDataLoaderConfig::class, 'string');

        $failingSerializer = static::createStub(AbstractContentDataLoaderConfigSerializer::class);
        $failingSerializer->method('decode')->willThrowException($internalFault);

        $provider = new DataLoaderConfigSerializerProvider(new ServiceLocator(['broken_source' => static fn () => $failingSerializer]));
        $codec = new StoredElementCodec($provider);

        $wire = self::baseWire([
            'dataRequirements' => [
                'products' => ['source' => 'broken_source', 'config' => []],
            ],
        ]);

        $this->expectExceptionObject($internalFault);

        $codec->decode($wire);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function dataRequirementKeyProvider(): iterable
    {
        $config = ['entity' => 'product', 'property' => 'productId'];

        yield 'the map key when the entry carries no inner key' => [
            ['source' => 'entity', 'config' => $config],
            'products',
        ];

        yield 'the map key when the inner key is an explicit null' => [
            ['key' => null, 'source' => 'entity', 'config' => $config],
            'products',
        ];

        yield 'the inner key when the entry carries both' => [
            ['key' => 'featured', 'source' => 'entity', 'config' => $config],
            'featured',
        ];
    }
}
