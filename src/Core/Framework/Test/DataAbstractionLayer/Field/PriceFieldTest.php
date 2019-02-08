<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\PriceFieldDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class PriceFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

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

        $smallId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();

        $data = [
            ['id' => $smallId, 'data' => ['gross' => 1.000000001, 'net' => 1.000000001]],
            ['id' => $bigId, 'data' => ['gross' => 1.000000009, 'net' => 1.000000009]],
        ];
        $this->getWriter()->insert(PriceFieldDefinition::class, $data, $context);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data', FieldSorting::ASCENDING));
        $result = $this->getRepository()->search(PriceFieldDefinition::class, $criteria, $context->getContext());
        static::assertEquals(2, $result->getTotal());
        static::assertEquals([$smallId, $bigId], $result->getIds(), 'smallId should be sorted to the first position');

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data', FieldSorting::DESCENDING));
        $result = $this->getRepository()->search(PriceFieldDefinition::class, $criteria, $context->getContext());
        static::assertEquals(2, $result->getTotal());
        static::assertEquals([$bigId, $smallId], $result->getIds(), 'bigId should be sorted to the first position');
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

    private function getRepository(): EntitySearcherInterface
    {
        return $this->getContainer()->get(EntitySearcherInterface::class);
    }
}
