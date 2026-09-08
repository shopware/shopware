<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * MySQL and MariaDB can only reference 61 tables in a single join. A criteria that
 * traverses many associations used to fail with error 1116, see issue #10770.
 *
 * @internal
 */
#[Package('framework')]
class JoinLimitTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MAX_TABLES_PER_JOIN = 61;

    public function testCriteriaWithManyAssociationsStaysBelowTheJoinLimit(): void
    {
        $criteria = new Criteria();
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new EqualsFilter($accessor, Uuid::randomHex()));
        }

        $slots = $this->outerJoinSlots($criteria);

        static::assertLessThanOrEqual(
            self::MAX_TABLES_PER_JOIN,
            $slots,
            \sprintf('The generated query joins %d tables, the database allows %d.', $slots, self::MAX_TABLES_PER_JOIN)
        );
    }

    public function testSortedAssociationKeepsItsJoin(): void
    {
        $criteria = new Criteria();
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new EqualsFilter($accessor, Uuid::randomHex()));
        }
        // the sorting reads from the joined alias, so this association must not be
        // moved into a sub query even though the criteria spills
        $criteria->addSorting(new FieldSorting('product.prices.quantityStart'));

        $sql = $this->buildSql($criteria);

        static::assertStringContainsString('`product.prices`', $sql);
        static::assertLessThanOrEqual(self::MAX_TABLES_PER_JOIN, $this->outerJoinSlots($criteria));
    }

    public function testLargeCriteriaIsExecutedWithoutExceedingTheJoinLimit(): void
    {
        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new EqualsFilter($accessor, Uuid::randomHex()));
        }

        static::assertGreaterThan(
            self::MAX_TABLES_PER_JOIN,
            $this->joinsWithoutSpilling($criteria),
            'the criteria is supposed to exceed the join limit unless it spills into sub queries'
        );

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');

        // none of the random relations exist, so the product must not match - the
        // point is that the query can be executed at all instead of failing with
        // "1116 Too many tables"
        static::assertSame(
            [],
            array_values($repository->searchIds($criteria, Context::createDefaultContext())->getIds())
        );
    }

    public function testSpilledCriteriaStillMatchesTheProduct(): void
    {
        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addFilter(new EqualsFilter('product.categories.name', 'JoinLimit'));

        // the product has no parent and no canonical product, so none of these
        // relations exist and every negation holds
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter($accessor, Uuid::randomHex())]));
        }

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');

        static::assertSame(
            [$productId],
            array_values($repository->searchIds($criteria, Context::createDefaultContext())->getIds())
        );
    }

    public function testSpilledCriteriaKeepsNullChecksOnAssociations(): void
    {
        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));

        // The product has no tags, so this has to match. Resolved as an `EXISTS`
        // sub query it would stop matching, which is why a null check keeps its
        // left join even while the rest of the criteria spills.
        $criteria->addFilter(new EqualsFilter('product.tags.name', null));

        // enough association filters to push the criteria over the budget, all of
        // them satisfied by a product without a parent or a canonical product
        foreach ($this->manyAccessors() as $accessor) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter($accessor, Uuid::randomHex())]));
        }

        static::assertGreaterThan(
            self::MAX_TABLES_PER_JOIN,
            $this->joinsWithoutSpilling($criteria),
            'the criteria is supposed to exceed the join limit unless it spills into sub queries'
        );

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');

        static::assertSame(
            [$productId],
            array_values($repository->searchIds($criteria, Context::createDefaultContext())->getIds())
        );
    }

    /**
     * @return list<string>
     */
    private function manyAccessors(): array
    {
        $definition = static::getContainer()->get(ProductDefinition::class);

        $leaves = [];
        foreach ($definition->getFields() as $field) {
            if (!$field instanceof AssociationField) {
                continue;
            }

            if ($field->getPropertyName() === 'parent' || $field->getPropertyName() === 'canonicalProduct') {
                continue;
            }

            $reference = $field instanceof ManyToManyAssociationField
                ? $field->getToManyReferenceDefinition()
                : $field->getReferenceDefinition();

            // translation definitions have a composite primary key and no `id`
            if ($reference->getFields()->get('id') === null) {
                continue;
            }

            $leaves[] = $field->getPropertyName() . '.id';
        }

        $accessors = [];
        foreach (['parent.', 'canonicalProduct.', 'parent.parent.'] as $prefix) {
            foreach ($leaves as $leaf) {
                $accessors[] = $prefix . $leaf;
            }
        }

        return $accessors;
    }

    private function buildSql(Criteria $criteria): string
    {
        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $definition = static::getContainer()->get(ProductDefinition::class);
        $connection = static::getContainer()->get(Connection::class);

        return Context::createDefaultContext()->enableInheritance(
            function (Context $context) use ($builder, $definition, $connection, $criteria): string {
                $query = new QueryBuilder($connection);
                $query->select('product.id');

                return $builder->build($query, $definition, clone $criteria, $context)->getSQL();
            }
        );
    }

    private function outerJoinSlots(Criteria $criteria): int
    {
        return self::countOuterSlots($this->buildSql($criteria));
    }

    private function joinsWithoutSpilling(Criteria $criteria): int
    {
        // count what the query would join if every association stayed a real join
        $definition = static::getContainer()->get(ProductDefinition::class);

        $paths = [];
        foreach ($criteria->getFilters() as $filter) {
            foreach ($filter->getFields() as $field) {
                $parts = explode('.', $field);
                array_pop($parts);
                for ($i = 1; $i <= \count($parts); ++$i) {
                    $paths[implode('.', \array_slice($parts, 0, $i))] = true;
                }
            }
        }
        unset($paths[$definition->getEntityName()]);

        return \count($paths);
    }

    /**
     * Counts the entries of the outermost FROM/JOIN list. Tables inside sub queries
     * and derived tables live in a join nest of their own and do not count.
     */
    private static function countOuterSlots(string $sql): int
    {
        $depth = 0;
        $slots = 0;
        $length = \strlen($sql);

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];

            if ($char === '(') {
                ++$depth;

                continue;
            }

            if ($char === ')') {
                --$depth;

                continue;
            }

            if ($depth === 0 && preg_match('/^(FROM|JOIN)\s/i', substr($sql, $i, 5)) === 1) {
                ++$slots;
            }
        }

        return $slots;
    }

    private function createProduct(string $productId): void
    {
        static::getContainer()->get('product.repository')->create([[
            'id' => $productId,
            'productNumber' => $productId,
            'stock' => 1,
            'name' => 'JoinLimit product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'JoinLimit', 'taxRate' => 19],
            'categories' => [['id' => Uuid::randomHex(), 'name' => 'JoinLimit']],
        ]], Context::createDefaultContext());
    }
}
