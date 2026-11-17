<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ProductCrossSellingSerializer::class)]
class ProductCrossSellingSerializerTest extends TestCase
{
    public function testTheAssignedProductsOfARecordAreLookedUpInOneSearch(): void
    {
        $crossSellingId = Uuid::randomHex();
        $productIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $existingAssignmentId = Uuid::randomHex();

        $existing = new ProductCrossSellingAssignedProductsCollection([
            (new ProductCrossSellingAssignedProductsEntity())->assign([
                'id' => $existingAssignmentId,
                'crossSellingId' => $crossSellingId,
                'productId' => $productIds[1],
            ]),
        ]);

        // one result per assigned product, so a per-product lookup is counted rather than running the queue dry
        $assignedProductsRepository = new StaticEntityRepository(
            [$existing, $existing, $existing],
            new ProductCrossSellingAssignedProductsDefinition()
        );

        $serializer = new ProductCrossSellingSerializer($assignedProductsRepository);
        $serializer->setRegistry(new SerializerRegistry([], [new FieldSerializer()]));

        $definition = new ProductCrossSellingDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $record = [
            'id' => $crossSellingId,
            'assignedProducts' => implode('|', $productIds),
        ];

        $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));

        static::assertCount(1, $assignedProductsRepository->searchedCriteria);
        static::assertSame(
            [null, $existingAssignmentId, null],
            array_map(static fn (array $assigned) => $assigned['id'] ?? null, $result['assignedProducts'])
        );
    }
}
