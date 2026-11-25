<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class InheritanceUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private InheritanceUpdater $updater;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->updater = static::getContainer()->get(InheritanceUpdater::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->ids = new IdsCollection();
    }

    public function testVariantsResolveEveryInheritedAssociationToTheirParentUnlessTheyHaveTheirOwn(): void
    {
        $this->createProducts();
        $productIds = $this->ids->getList(['parent', 'inheriting-variant', 'own-variant']);
        $this->clearInheritedColumns($productIds, Defaults::LIVE_VERSION);

        $this->updater->update(
            ProductDefinition::ENTITY_NAME,
            [...array_values($productIds), $this->ids->get('inheriting-variant'), ''],
            Context::createDefaultContext()
        );

        $rows = $this->fetchInheritedColumns($productIds, Defaults::LIVE_VERSION);

        foreach ($this->toManyColumns() as $column) {
            static::assertSame($this->ids->get('parent'), $rows['parent'][$column], \sprintf('The parent reads "%s" from itself.', $column));
            static::assertSame($this->ids->get('parent'), $rows['inheriting-variant'][$column], \sprintf('A variant without its own "%s" reads them from its parent.', $column));
        }
        static::assertSame($this->ids->get('own-variant'), $rows['own-variant']['tags'], 'A variant with its own tags reads them from itself.');
        static::assertSame($this->ids->get('parent'), $rows['own-variant']['prices'], 'A variant without its own prices reads them from its parent.');

        static::assertSame($this->ids->get('manufacturer'), $rows['parent']['manufacturer']);
        static::assertSame($this->ids->get('manufacturer'), $rows['inheriting-variant']['manufacturer'], 'A variant without its own manufacturer inherits the parent\'s.');
        static::assertSame($this->ids->get('own-manufacturer'), $rows['own-variant']['manufacturer'], 'A variant keeps its own manufacturer.');

        foreach (['parent', 'inheriting-variant', 'own-variant'] as $key) {
            static::assertSame($this->ids->get('tax'), $rows[$key]['tax'], \sprintf('"%s" resolves the tax of the product family.', $key));
            static::assertSame($this->ids->get('unit'), $rows[$key]['unit'], \sprintf('"%s" resolves the unit of the product family.', $key));
        }
    }

    public function testUpdateInAVersionLeavesTheLiveRowsUntouched(): void
    {
        $this->createProducts();
        $productIds = $this->ids->getList(['parent', 'inheriting-variant', 'own-variant']);

        $versionId = $this->productRepository->createVersion($this->ids->get('parent'), Context::createDefaultContext());
        static::assertCount(3, $this->fetchInheritedColumns($productIds, $versionId), 'The version must contain the parent and both variants.');

        $this->clearInheritedColumns($productIds, Defaults::LIVE_VERSION);
        $this->clearInheritedColumns($productIds, $versionId);

        $this->updater->update(
            ProductDefinition::ENTITY_NAME,
            array_values($productIds),
            Context::createDefaultContext()->createWithVersionId($versionId)
        );

        $versioned = $this->fetchInheritedColumns($productIds, $versionId);
        static::assertSame($this->ids->get('parent'), $versioned['inheriting-variant']['tags']);
        static::assertSame($this->ids->get('manufacturer'), $versioned['inheriting-variant']['manufacturer']);
        static::assertSame($this->ids->get('tax'), $versioned['parent']['tax']);

        foreach ($this->fetchInheritedColumns($productIds, Defaults::LIVE_VERSION) as $key => $row) {
            foreach ($row as $column => $value) {
                static::assertNull($value, \sprintf('The live "%s" of "%s" must not be touched by an update in another version.', $column, $key));
            }
        }
    }

    private function createProducts(): void
    {
        $this->productRepository->create([
            [
                'id' => $this->ids->create('parent'),
                'productNumber' => $this->ids->get('parent'),
                'stock' => 10,
                'name' => 'parent',
                'tax' => ['id' => $this->ids->create('tax'), 'name' => 'tax', 'taxRate' => 19],
                'unit' => ['id' => $this->ids->create('unit'), 'shortCode' => 'pc', 'name' => 'piece'],
                'manufacturer' => ['id' => $this->ids->create('manufacturer'), 'name' => 'manufacturer'],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tags' => [['id' => $this->ids->create('parent-tag'), 'name' => 'parent-tag']],
            ],
            [
                'id' => $this->ids->create('inheriting-variant'),
                'productNumber' => $this->ids->get('inheriting-variant'),
                'parentId' => $this->ids->get('parent'),
                'stock' => 10,
            ],
            [
                'id' => $this->ids->create('own-variant'),
                'productNumber' => $this->ids->get('own-variant'),
                'parentId' => $this->ids->get('parent'),
                'stock' => 10,
                'manufacturer' => ['id' => $this->ids->create('own-manufacturer'), 'name' => 'own-manufacturer'],
                'tags' => [['id' => $this->ids->create('own-tag'), 'name' => 'own-tag']],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @param array<string, string> $productIds
     */
    private function clearInheritedColumns(array $productIds, string $versionId): void
    {
        $this->connection->executeStatement(
            \sprintf(
                'UPDATE `product` SET %s WHERE `id` IN (:ids) AND `version_id` = :version',
                implode(', ', array_map(static fn (string $column) => \sprintf('`%s` = NULL', $column), $this->inheritedColumns()))
            ),
            ['ids' => Uuid::fromHexToBytesList(array_values($productIds)), 'version' => Uuid::fromHexToBytes($versionId)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param array<string, string> $productIds
     *
     * @return array<string, array<string, string|null>>
     */
    private function fetchInheritedColumns(array $productIds, string $versionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT LOWER(HEX(`id`)) AS `id`, %s FROM `product` WHERE `id` IN (:ids) AND `version_id` = :version',
                implode(', ', array_map(static fn (string $column) => \sprintf('LOWER(HEX(`%1$s`)) AS `%1$s`', $column), $this->inheritedColumns()))
            ),
            ['ids' => Uuid::fromHexToBytesList(array_values($productIds)), 'version' => Uuid::fromHexToBytes($versionId)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $keys = array_flip($productIds);
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$keys[$row['id']]] = array_diff_key($row, ['id' => true]);
        }

        return $byKey;
    }

    /**
     * @return list<string>
     */
    private function inheritedColumns(): array
    {
        return array_values(array_map(
            static fn (Field $field) => $field->getPropertyName(),
            $this->inheritedAssociations()->getElements()
        ));
    }

    /**
     * @return list<string>
     */
    private function toManyColumns(): array
    {
        return array_values(array_map(
            static fn (Field $field) => $field->getPropertyName(),
            $this->inheritedAssociations()->filter(static fn (Field $field) => !$field instanceof ManyToOneAssociationField)->getElements()
        ));
    }

    private function inheritedAssociations(): CompiledFieldCollection
    {
        return static::getContainer()->get(ProductDefinition::class)->getFields()
            ->filter(static fn (Field $field) => $field instanceof AssociationField && $field->is(Inherited::class));
    }
}
