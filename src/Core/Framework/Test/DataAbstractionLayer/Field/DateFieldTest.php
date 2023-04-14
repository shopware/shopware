<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class DateFieldTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _date_field_test;
CREATE TABLE `_date_field_test` (
  `id` BINARY(16) NOT NULL,
  `date` DATE NOT NULL,
  `date_nullable` DATE NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeStatement($nullableTable);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `_date_field_test`');

        parent::tearDown();
    }

    public function testDateFieldIsNullableAndFormat(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $date = new \DateTime();

        $data = [
            'id' => $id,
            'date' => $date,
            'date_nullable' => null,
        ];

        $this->getWriter()->insert($this->registerDefinition(DateDefinition::class), [$data], $context);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM `_date_field_test`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertNull($data[0]['date_nullable']);
        static::assertSame($date->format(Defaults::STORAGE_DATE_FORMAT), $data[0]['date']);
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
}
