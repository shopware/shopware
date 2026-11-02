<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

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

        /** @var StaticEntityRepository<ProductVisibilityCollection> $visibilityRepository */
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
}
