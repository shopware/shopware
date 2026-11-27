<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductSerializer::class)]
class ProductSerializerTest extends TestCase
{
    public function testTheVisibilitiesOfAProductAreLookedUpInOneSearch(): void
    {
        $productId = Uuid::randomHex();
        $salesChannelIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $existing = new ProductVisibilityCollection(array_map(
            static fn (string $salesChannelId) => (new ProductVisibilityEntity())->assign([
                'id' => Uuid::randomHex(),
                'productId' => $productId,
                'salesChannelId' => $salesChannelId,
            ]),
            $salesChannelIds
        ));

        $visibilityRepository = new StaticEntityRepository([$existing], new ProductVisibilityDefinition());

        $serializer = new ProductSerializer(
            $visibilityRepository,
            StaticEntityRepository::of(SalesChannelCollection::class),
            StaticEntityRepository::of(ProductMediaCollection::class),
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = [
            'id' => $productId,
            'visibilities' => ['all' => implode('|', $salesChannelIds)],
        ];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertCount(1, $visibilityRepository->searchedCriteria);

        $resolved = array_column($result['visibilities'], 'id', 'salesChannelId');
        static::assertSame(
            array_column($existing->getElements(), 'id', 'salesChannelId'),
            $resolved
        );
    }

    /**
     * @param array<string, mixed> $idCell
     */
    #[DataProvider('recordsWithoutAProductIdProvider')]
    public function testTheVisibilitiesOfARecordWithoutAProductAreNotLookedUp(array $idCell): void
    {
        $salesChannelIds = [Uuid::randomHex(), Uuid::randomHex()];

        $visibilityRepository = StaticEntityRepository::of(ProductVisibilityCollection::class, [], new ProductVisibilityDefinition());

        $serializer = new ProductSerializer(
            $visibilityRepository,
            StaticEntityRepository::of(SalesChannelCollection::class),
            StaticEntityRepository::of(ProductMediaCollection::class),
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = $idCell + ['visibilities' => ['all' => implode('|', $salesChannelIds)]];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertSame([], $visibilityRepository->searchedCriteria);
        static::assertSame($salesChannelIds, array_column($result['visibilities'], 'salesChannelId'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function recordsWithoutAProductIdProvider(): iterable
    {
        yield 'the column is missing' => [[]];
        // CsvReader hands an empty cell over as an empty string, not as null
        yield 'the cell is empty' => [['id' => '']];
        // a non string value skips the empty check of the field serializer and normalizes to an empty id
        yield 'the value is false' => [['id' => false]];
    }

    public function testTheMediaOfARecordIsNotReadFromAnEmptyArray(): void
    {
        $serializer = new ProductSerializer(
            StaticEntityRepository::of(ProductVisibilityCollection::class),
            StaticEntityRepository::of(SalesChannelCollection::class),
            StaticEntityRepository::of(ProductMediaCollection::class),
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = ['id' => Uuid::randomHex(), 'media' => []];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertSame([], $result['media']);
    }

    public function testTheConfiguratorSettingsOfAProductAreLookedUpInOneSearch(): void
    {
        $parentId = Uuid::randomHex();
        $optionIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $existingSettingId = Uuid::randomHex();

        $existing = new ProductConfiguratorSettingCollection([
            (new ProductConfiguratorSettingEntity())->assign([
                'id' => $existingSettingId,
                'productId' => $parentId,
                'optionId' => $optionIds[1],
            ]),
        ]);

        // one result per option, so a per-option lookup is counted rather than running the queue dry
        $configuratorSettingRepository = new StaticEntityRepository([$existing, $existing, $existing], new ProductConfiguratorSettingDefinition());

        $serializer = new ProductSerializer(
            StaticEntityRepository::of(ProductVisibilityCollection::class),
            StaticEntityRepository::of(SalesChannelCollection::class),
            StaticEntityRepository::of(ProductMediaCollection::class),
            $configuratorSettingRepository
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = [
            'id' => Uuid::randomHex(),
            'parentId' => $parentId,
            'options' => implode('|', $optionIds),
        ];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertCount(1, $configuratorSettingRepository->searchedCriteria);
        static::assertSame([
            ['optionId' => $optionIds[0], 'product' => ['id' => $parentId]],
            ['optionId' => $optionIds[1], 'product' => ['id' => $parentId], 'id' => $existingSettingId],
            ['optionId' => $optionIds[2], 'product' => ['id' => $parentId]],
        ], $result['configuratorSettings']);
    }

    public function testTheSalesChannelNamesOfEveryVisibilityKeyAreResolvedInOneSearch(): void
    {
        $channels = ['Storefront' => Uuid::randomHex(), 'Headless' => Uuid::randomHex(), 'Feed' => Uuid::randomHex()];

        $salesChannels = new SalesChannelCollection(array_map(
            static fn (string $name, string $id) => (new SalesChannelEntity())->assign(['id' => $id, 'name' => $name]),
            array_keys($channels),
            array_values($channels)
        ));

        // one result per visibility key, so a per-key lookup is counted rather than running the queue dry
        $salesChannelRepository = new StaticEntityRepository(
            [$salesChannels, $salesChannels, $salesChannels],
            new SalesChannelDefinition()
        );

        $serializer = new ProductSerializer(
            StaticEntityRepository::of(ProductVisibilityCollection::class),
            $salesChannelRepository,
            StaticEntityRepository::of(ProductMediaCollection::class),
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = ['visibilities' => ['all' => 'Storefront', 'link' => 'Headless', 'search' => 'Feed']];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertCount(1, $salesChannelRepository->searchedCriteria);
        static::assertSame(array_values($channels), array_column($result['visibilities'], 'salesChannelId'));
    }

    public function testEverySalesChannelSharingAVisibilityNameIsResolved(): void
    {
        $salesChannelIds = [Uuid::randomHex(), Uuid::randomHex()];

        $salesChannels = new SalesChannelCollection(array_map(
            static fn (string $id) => (new SalesChannelEntity())->assign(['id' => $id, 'name' => 'Storefront']),
            $salesChannelIds
        ));

        $salesChannelRepository = new StaticEntityRepository([$salesChannels], new SalesChannelDefinition());

        $serializer = new ProductSerializer(
            StaticEntityRepository::of(ProductVisibilityCollection::class),
            $salesChannelRepository,
            StaticEntityRepository::of(ProductMediaCollection::class),
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = ['visibilities' => ['all' => 'Storefront']];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertSame($salesChannelIds, array_column($result['visibilities'], 'salesChannelId'));
    }

    public function testASalesChannelNameMatchesRegardlessOfCase(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannels = new SalesChannelCollection([
            (new SalesChannelEntity())->assign(['id' => $salesChannelId, 'name' => 'Storefront']),
        ]);

        $salesChannelRepository = new StaticEntityRepository([$salesChannels], new SalesChannelDefinition());

        $serializer = new ProductSerializer(
            StaticEntityRepository::of(ProductVisibilityCollection::class),
            $salesChannelRepository,
            StaticEntityRepository::of(ProductMediaCollection::class),
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = ['visibilities' => ['all' => 'storefront']];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertSame([$salesChannelId], array_column($result['visibilities'], 'salesChannelId'));
    }

    public function testTheMediaOfARecordAreLookedUpInOneSearch(): void
    {
        $productId = Uuid::randomHex();
        $mediaIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $existingProductMediaId = Uuid::randomHex();

        $existing = new ProductMediaCollection([
            (new ProductMediaEntity())->assign([
                'id' => $existingProductMediaId,
                'productId' => $productId,
                'mediaId' => $mediaIds[1],
            ]),
        ]);

        // one result per url, so a per-url lookup is counted rather than running the queue dry
        $productMediaRepository = new StaticEntityRepository([$existing, $existing, $existing], new ProductMediaDefinition());

        $serializer = new ProductSerializer(
            StaticEntityRepository::of(ProductVisibilityCollection::class),
            StaticEntityRepository::of(SalesChannelCollection::class),
            $productMediaRepository,
            StaticEntityRepository::of(ProductConfiguratorSettingCollection::class)
        );
        $serializer->setRegistry(new SerializerRegistry([new StaticMediaSerializer($mediaIds)], [new FieldSerializer()]));

        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class, ProductMediaDefinition::class, MediaDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );
        $definition = $registry->get(ProductDefinition::class);

        $record = [
            'id' => $productId,
            'media' => implode('|', array_fill(0, \count($mediaIds), 'https://example.com/image.png')),
        ];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertCount(1, $productMediaRepository->searchedCriteria);
        static::assertSame(
            [null, $existingProductMediaId, null],
            array_map(static fn (array $productMedia) => $productMedia['id'] ?? null, $result['media'])
        );
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class StaticMediaSerializer extends EntitySerializer
{
    /**
     * @param list<string> $mediaIds
     */
    public function __construct(private array $mediaIds)
    {
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity): iterable
    {
        yield 'id' => array_shift($this->mediaIds);
    }

    public function supports(string $entity): bool
    {
        return $entity === MediaDefinition::ENTITY_NAME;
    }
}
