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
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementWiringDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Routing of a decoded entry to the object that owns its config: a data requirement to its source's config
 * serializer, and a provider to its declared distribution strategy's config. The distribution rows reach
 * {@see StoredElementWiringDecoder}, which the codec composes, which is why it is covered here too.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredElementCodec::class)]
#[CoversClass(StoredElementWiringDecoder::class)]
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

    /**
     * @param array<string, mixed> $provider
     * @param class-string<DistributionConfig> $expected
     * @param array<string, mixed> $expectedConfig
     */
    #[DataProvider('distributionStrategyProvider')]
    #[TestDox('builds the config of $_dataName')]
    public function testDecodeDispatchesEveryDistributionStrategy(array $provider, string $expected, array $expectedConfig): void
    {
        $element = $this->codec()->decode(self::baseWire(['providesContext' => ['product' => $provider]]));

        $config = $element->contextDefinitions->getAllProviders()['product']->distributionConfig;

        static::assertInstanceOf($expected, $config);
        static::assertSame($expectedConfig, $config->toArray());
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

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<DistributionConfig>, array<string, mixed>}>
     */
    public static function distributionStrategyProvider(): iterable
    {
        yield 'a broadcast provider' => [
            ['type' => 'collection', 'distribution' => 'broadcast'],
            BroadcastDistributionConfig::class,
            ['distribution' => 'broadcast', 'consumerAlias' => null],
        ];

        yield 'an indexed provider' => [
            ['type' => 'collection', 'distribution' => 'indexed'],
            IndexedDistributionConfig::class,
            ['distribution' => 'indexed', 'consumerAlias' => null],
        ];

        yield 'an iterator provider' => [
            ['type' => 'collection', 'distribution' => 'iterator'],
            IteratorDistributionConfig::class,
            ['distribution' => 'iterator', 'consumerAlias' => null],
        ];

        yield 'a keyed provider' => [
            ['type' => 'single', 'distribution' => 'keyed', 'keyProperty' => 'sku'],
            KeyedDistributionConfig::class,
            ['distribution' => 'keyed', 'keyProperty' => 'sku', 'consumerAlias' => null],
        ];

        yield 'a sliced provider' => [
            ['type' => 'collection', 'distribution' => 'sliced', 'sliceSize' => 4],
            SlicedDistributionConfig::class,
            ['distribution' => 'sliced', 'sliceSize' => 4, 'consumerAlias' => null],
        ];
    }
}
