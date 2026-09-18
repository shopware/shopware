<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(PrimaryKeyResolver::class)]
class PrimaryKeyResolverTest extends TestCase
{
    public function testWarmUpResolvesAWholeWindowWithOneQuery(): void
    {
        $definition = new ProductDefinition();

        // one prepared search for the whole window
        $repository = $this->createRepository($definition, [
            new EntityCollection([$this->createPartial(Uuid::randomHex(), 'SW-1')]),
        ]);

        $resolver = $this->createResolver($repository);

        $resolver->warmUp($this->createConfig(), $definition, [
            ['productNumber' => 'SW-1'],
            ['productNumber' => 'SW-2'],
        ]);

        // the single prepared search was consumed, so both values were resolved with one query
        static::assertSame([], $repository->searches);
    }

    /**
     * @param array<mixed> $searches
     *
     * @return StaticEntityRepository<ProductCollection>
     */
    private function createRepository(ProductDefinition $definition, array $searches): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ProductCollection> $repository */
        $repository = new StaticEntityRepository($searches, $definition);

        return $repository;
    }

    /**
     * @param StaticEntityRepository<ProductCollection> $repository
     */
    private function createResolver(StaticEntityRepository $repository): PrimaryKeyResolver
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $fieldSerializer = $this->createMock(AbstractFieldSerializer::class);
        $fieldSerializer->method('deserialize')->willReturnCallback(
            static fn (Config $config, $field, $value) => $value
        );

        return new PrimaryKeyResolver($registry, $fieldSerializer);
    }

    private function createPartial(string $id, string $productNumber): PartialEntity
    {
        $entity = new PartialEntity();
        $entity->assign(['id' => $id, 'productNumber' => $productNumber]);
        $entity->setUniqueIdentifier($id);

        return $entity;
    }

    private function createConfig(): Config
    {
        return new Config([], [], [['entityName' => ProductDefinition::ENTITY_NAME, 'mappedKey' => 'productNumber']]);
    }
}
