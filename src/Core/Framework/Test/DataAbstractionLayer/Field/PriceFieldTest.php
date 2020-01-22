<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\PriceFieldDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class PriceFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `data` longtext NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeUpdate($nullableTable);
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeUpdate('DROP TABLE `_test_nullable`');
    }

    public function testPriceOrdering(): void
    {
        $context = $this->createWriteContext();

        $smallId = Uuid::randomHex();
        $bigId = Uuid::randomHex();

        $data = [
            ['id' => $smallId, 'data' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1.000000001, 'net' => 1.000000001, 'linked' => true]]],
            ['id' => $bigId, 'data' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1.000000009, 'net' => 1.000000009, 'linked' => true]]],
        ];
        $this->getWriter()->insert($this->registerDefinition(PriceFieldDefinition::class), $data, $context);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data', FieldSorting::ASCENDING));
        $result = $this->getSearcher()->search($this->registerDefinition(PriceFieldDefinition::class), $criteria, $context->getContext());
        static::assertEquals(2, $result->getTotal());
        static::assertEquals([$smallId, $bigId], $result->getIds(), 'smallId should be sorted to the first position');

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data', FieldSorting::DESCENDING));
        $result = $this->getSearcher()->search($this->registerDefinition(PriceFieldDefinition::class), $criteria, $context->getContext());
        static::assertEquals(2, $result->getTotal());
        static::assertEquals([$bigId, $smallId], $result->getIds(), 'bigId should be sorted to the first position');
    }

    public function testListPriceLoading(): void
    {
        $context = $this->createWriteContext();

        $ids = new TestDataCollection(Context::createDefaultContext());

        $data = [
            [
                'id' => $ids->create('with-was'),
                'data' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 1,
                        'net' => 1,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => 2,
                            'net' => 2,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];

        $definition = $this->registerDefinition(PriceFieldDefinition::class);
        $this->getWriter()->insert($definition, $data, $context);

        $entity = $this->getContainer()->get(EntityReaderInterface::class)
            ->read($definition, new Criteria([$ids->get('with-was')]), $ids->getContext())
            ->get($ids->get('with-was'));

        $price = $entity->get('data');

        /** @var PriceCollection $price */
        static::assertInstanceOf(PriceCollection::class, $price);

        $price = $price->getCurrencyPrice(Defaults::CURRENCY);
        /** @var Price $price */
        static::assertInstanceOf(Price::class, $price);

        static::assertInstanceOf(Price::class, $price->getListPrice());
        static::assertEquals(2, $price->getListPrice()->getNet());
    }

    public function testListPriceInCriteriaParts(): void
    {
        $context = $this->createWriteContext();

        $ids = new TestDataCollection(Context::createDefaultContext());

        $data = [
            [
                'id' => $ids->create('was'),
                'data' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 1,
                        'net' => 1,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => 2,
                            'net' => 2,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
            [
                'id' => $ids->create('was-2'),
                'data' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 1,
                        'net' => 1,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => 10,
                            'net' => 10,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];

        $definition = $this->registerDefinition(PriceFieldDefinition::class);
        $this->getWriter()->insert($definition, $data, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('data.listPrice', 2));

        $result = $this->getSearcher()
            ->search($definition, $criteria, $ids->getContext());

        static::assertCount(1, $result->getIds());
        static::assertTrue($result->has($ids->get('was')));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('data.listPrice', 3));

        $result = $this->getSearcher()
            ->search($definition, $criteria, $ids->getContext());

        static::assertCount(0, $result->getIds());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data.listPrice', FieldSorting::DESCENDING));

        $result = $this->getSearcher()
            ->search($definition, $criteria, $ids->getContext());

        static::assertEquals(
            [
                $ids->get('was-2'),
                $ids->get('was'),
            ],
            $result->getIds()
        );
    }

    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        return $context;
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }

    private function getSearcher(): EntitySearcherInterface
    {
        return $this->getContainer()->get(EntitySearcherInterface::class);
    }
}
