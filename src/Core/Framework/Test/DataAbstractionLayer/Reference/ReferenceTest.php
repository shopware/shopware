<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestDefinition\ReferenceTestDocumentDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestDefinition\ReferenceTestDocumentTypeDefinition;
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

        $documentTable = <<<EOF
DROP TABLE IF EXISTS _reference_test_document;
CREATE TABLE `_reference_test_document` (
  `id` BINARY(16) NOT NULL,
  `document_type_technical_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $documentTypeTable = <<<EOF
DROP TABLE IF EXISTS _reference_test_document_type;
CREATE TABLE `_reference_test_document_type` (
  `id` BINARY(16) NOT NULL,
  `technical_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  PRIMARY KEY `id` (`id`),
  CONSTRAINT `_reference_test_document_type.technical_name.uniqe` UNIQUE (`technical_name`)
);
EOF;
        $this->connection->executeUpdate($documentTable);
        $this->connection->executeUpdate($documentTypeTable);

        $this->getContainer()->set(NonUuidFkTestFieldSerializer::class, new NonUuidFkTestFieldSerializer($this->getContainer()->get('debug.validator')));

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeUpdate('DROP TABLE `_reference_test_document`');
        $this->connection->executeUpdate('DROP TABLE `_reference_test_document_type`');

        parent::tearDown();
    }

    public function testCanReferenceViaNonPrimaryUniqueKey(): void
    {
        $technicalName = 'din-a4';
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $data = [
            'technicalName' => $technicalName,
        ];
        $this->getWriter()->insert($this->registerDefinition(ReferenceTestDocumentTypeDefinition::class), [$data], $context);

        $data = [
            'documentType' => [
                // At this point, i don't know the ID of the document type, so the technical name is used instead
                // because it is unique also.
                'technicalName' => $technicalName,
            ],
        ];
        $this->getWriter()->insert($this->registerDefinition(ReferenceTestDocumentDefinition::class), [$data], $context);
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
