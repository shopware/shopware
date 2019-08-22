<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestDefinition\FkReferencedTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestDefinition\FkReferencingTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestFieldSerializer\NonUuidFkTestFieldSerializer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ReferenceTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $referencingTable = <<<EOF
DROP TABLE IF EXISTS _fk_field_test_referencing;
CREATE TABLE `_fk_field_test_referencing` (
  `id` BINARY(16) NOT NULL,
  `referenced_technical_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $referencedTable = <<<EOF
DROP TABLE IF EXISTS _fk_field_test_referenced;
CREATE TABLE `_fk_field_test_referenced` (
  `id` BINARY(16) NOT NULL,
  `technical_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY `id` (`id`),
  CONSTRAINT `_fk_field_test_referenced.technical_name.uniqe` UNIQUE (`technical_name`)
);
EOF;
        $this->connection->executeUpdate($referencingTable);
        $this->connection->executeUpdate($referencedTable);

        $this->getContainer()->set(NonUuidFkTestFieldSerializer::class, new NonUuidFkTestFieldSerializer($this->getContainer()->get('debug.validator')));

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeUpdate('DROP TABLE `_fk_field_test_referencing`');
        $this->connection->executeUpdate('DROP TABLE `_fk_field_test_referenced`');

        parent::tearDown();
    }

    public function testCanReferenceViaNonPrimaryUniqueKey(): void
    {
        $technicalName = __FUNCTION__;
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $data = [
            'technicalName' => $technicalName,
        ];
        $this->getWriter()->insert($this->registerDefinition(FkReferencedTestDefinition::class), [$data], $context);

        $data = [
            'referenced' => [
                'technicalName' => $technicalName,
            ],
        ];
        $data2 = [
            'technicalName' => $technicalName,
        ];
        $this->getWriter()->insert($this->registerDefinition(FkReferencingTestDefinition::class), [$data, $data2], $context);
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
