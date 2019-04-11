<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InsufficientWritePermissionException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedReferenceDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedRelationDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedTranslatedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class WriteProtectedFieldTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->connection->executeUpdate('DROP TABLE IF EXISTS `_test_nullable`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `_test_nullable_reference`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `_test_nullable_translation`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `_test_relation`');

        $nullableTable = <<<EOF
CREATE TABLE `_test_relation` (
  `id` binary(16) NOT NULL,
  PRIMARY KEY `id` (`id`)
);

CREATE TABLE `_test_nullable_reference` (
  `wp_id` binary(16) NOT NULL,
  `relation_id` binary(16) NOT NULL,
  PRIMARY KEY `pk` (`wp_id`, `relation_id`)
);
            
CREATE TABLE `_test_nullable_translation` (
  `_test_nullable_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `protected` varchar(255) NULL,
  `system_protected` varchar(255) NULL,
  PRIMARY KEY `pk` (`_test_nullable_id`, `language_id`)
);

CREATE TABLE `_test_nullable` (
  `id` binary(16) NOT NULL,
  `relation_id` binary(16) NULL,
  `system_relation_id` binary(16) NULL,
  `protected` varchar(255) NULL,
  `system_protected` varchar(255) NULL,
  PRIMARY KEY `id` (`id`),
  FOREIGN KEY `fk` (`relation_id`) REFERENCES _test_relation (`id`)
);
EOF;
        $this->connection->executeUpdate($nullableTable);
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();

        $this->connection->executeUpdate('DROP TABLE `_test_nullable`');
        $this->connection->executeUpdate('DROP TABLE `_test_relation`');
        $this->connection->executeUpdate('DROP TABLE `_test_nullable_translation`');
        $this->connection->executeUpdate('DROP TABLE `_test_nullable_reference`');

        parent::tearDown();
    }

    public function testWriteWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();

        $data = [
            'id' => $id,
            'protected' => 'foobar',
        ];

        $ex = null;
        try {
            $this->getWriter()->insert($definition, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());
        static::assertEquals('This field is write-protected.', $this->getValidationExceptionMessage($ex));

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InsufficientWritePermissionException::class, \get_class($fieldException));
        static::assertEquals('/protected', $fieldException->getPath());
    }

    public function testWriteWithoutProtectedField(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();

        $data = [
            'id' => $id,
        ];

        $this->getWriter()->insert($definition, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertEmpty($data[0]['protected']);
    }

    public function testWriteWithPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();

        $data = [
            'id' => $id,
            'systemProtected' => 'foobar',
        ];

        $this->getWriter()->insert($definition, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertEquals('foobar', $data[0]['system_protected']);
    }

    public function testWriteManyToOneWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();

        $data = [
            'id' => $id,
            'relation' => [
                'id' => $id,
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert($definition, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());
        static::assertEquals('This field is write-protected.', $this->getValidationExceptionMessage($ex, 'relation'));

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InsufficientWritePermissionException::class, \get_class($fieldException));
        static::assertEquals('/relation', $fieldException->getPath());
    }

    public function testWriteManyToOneWithPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();

        $data = [
            'id' => $id,
            'systemRelation' => [
                'id' => $id,
            ],
        ];

        $this->getContainer()->set(WriteProtectedDefinition::class, $definition);
        $this->getContainer()->set(WriteProtectedRelationDefinition::class, new WriteProtectedRelationDefinition());
        $this->getWriter()->insert($definition, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['system_relation_id']);
    }

    public function testWriteOneToManyWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedRelationDefinition();

        $data = [
            'id' => $id,
            'wp' => [
                [
                    'id' => $id,
                ],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert($definition, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());
        static::assertEquals('This field is write-protected.', $this->getValidationExceptionMessage($ex, 'wp'));

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InsufficientWritePermissionException::class, \get_class($fieldException));
        static::assertEquals('/wp', $fieldException->getPath());
    }

    public function testWriteOneToManyWithPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedRelationDefinition();

        $data = [
            'id' => $id,
            'systemWp' => [
                [
                    'systemProtected' => 'foobar',
                    'relationId' => $id,
                ],
            ],
        ];

        $this->getWriter()->insert($definition, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['relation_id']);
    }

    public function testWriteManyToManyWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();

        $data = [
            'id' => $id,
            'relations' => [
                [
                    'id' => $id2,
                ],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert($definition, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());
        static::assertEquals('This field is write-protected.', $this->getValidationExceptionMessage($ex, 'relations'));

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InsufficientWritePermissionException::class, \get_class($fieldException));
        static::assertEquals('/relations', $fieldException->getPath());
    }

    public function testWriteManyToManyWithPermission(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedDefinition();
        $this->getContainer()->set(WriteProtectedReferenceDefinition::class, new WriteProtectedReferenceDefinition());

        $data = [
            'id' => $id,
            'systemRelations' => [
                [
                    'id' => $id2,
                ],
            ],
        ];

        $this->getWriter()->insert($definition, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable_reference`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['wp_id']);
        static::assertEquals(Uuid::fromHexToBytes($id2), $data[0]['relation_id']);
    }

    public function testWriteTranslationWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedTranslatedDefinition();

        $data = [
            'id' => $id,
            'protected' => 'foobar',
        ];

        $ex = null;
        try {
            $this->getWriter()->insert($definition, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());
        static::assertEquals('This field is write-protected.', $this->getValidationExceptionMessage($ex));

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InsufficientWritePermissionException::class, \get_class($fieldException));
        static::assertEquals('/protected', $fieldException->getPath());
    }

    public function testWriteTranslationWithPermission(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();
        $definition = new WriteProtectedTranslatedDefinition();
        $this->getContainer()->set(WriteProtectedTranslationDefinition::class, new WriteProtectedTranslationDefinition());

        $data = [
            'id' => $id,
            'systemProtected' => 'foobar',
        ];

        $this->getWriter()->insert($definition, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable_translation`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['_test_nullable_id']);
        static::assertEquals('foobar', $data[0]['system_protected']);
    }

    protected function createWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }

    private function getValidationExceptionMessage(WriteStackException $ex, string $field = 'protected'): string
    {
        return $ex->toArray()['/' . $field]['insufficient-permission'][0]['message'];
    }
}
