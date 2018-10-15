<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Write\EntityWriter;
use Shopware\Core\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Core\Framework\ORM\Write\FieldException\InsufficientWritePermissionException;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\WriteProtectedDefinition;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\WriteProtectedRelationDefinition;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\WriteProtectedTranslatedDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class WriteProtectedFieldTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_relation;
CREATE TABLE `_test_relation` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NULL,
  PRIMARY KEY `id` (`id`)
);

DROP TABLE IF EXISTS _test_nullable_reference;
CREATE TABLE `_test_nullable_reference` (
  `wp_id` binary(16) NOT NULL,
  `wp_tenant_id` binary(16) NULL,
  `relation_id` binary(16) NOT NULL,
  `relation_tenant_id` binary(16) NULL,
  PRIMARY KEY `pk` (`wp_id`, `relation_id`)
);
            
DROP TABLE IF EXISTS _test_nullable_translation;
CREATE TABLE `_test_nullable_translation` (
  `wp_id` binary(16) NOT NULL,
  `wp_tenant_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `language_tenant_id` binary(16) NOT NULL,
  `protected` varchar(255) NULL,
  PRIMARY KEY `pk` (`wp_id`, `wp_tenant_id`, `language_id`, `language_tenant_id`)
);

DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` binary(16) NOT NULL,
  `tenant_id` binary(16) NULL,
  `relation_id` binary(16) NULL,
  `relation_tenant_id` binary(16) NULL,
  `protected` varchar(255) NULL,
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
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'protected' => 'foobar',
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);
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
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
        ];

        $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertEmpty($data[0]['protected']);
    }

    public function testWriteWithPermission(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();
        $context->getContext()->getWriteProtection()->allow('WriteProtected');

        $data = [
            'id' => $id->getHex(),
            'protected' => 'foobar',
        ];

        $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertEquals('foobar', $data[0]['protected']);
    }

    public function testWriteManyToOneWithoutPermission(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'relation' => [
                'id' => $id->getHex(),
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);
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
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();
        $context->getContext()->getWriteProtection()->allow('WriteProtected');

        $data = [
            'id' => $id->getHex(),
            'relation' => [
                'id' => $id->getHex(),
            ],
        ];

        $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertEquals($id->getBytes(), $data[0]['relation_id']);
    }

    public function testWriteOneToManyWithoutPermission(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'wp' => [
                [
                    'id' => $id->getHex(),
                ],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(WriteProtectedRelationDefinition::class, [$data], $context);
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
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();
        $context->getContext()->getWriteProtection()->allow('WriteProtected');

        $data = [
            'id' => $id->getHex(),
            'wp' => [
                [
                    'protected' => 'foobar',
                    'relationId' => $id->getHex(),
                ],
            ],
        ];

        $this->getWriter()->insert(WriteProtectedRelationDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['relation_id']);
    }

    public function testWriteManyToManyWithoutPermission(): void
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'relations' => [
                [
                    'id' => $id2->getHex(),
                ],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);
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
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $context = $this->createWriteContext();
        $context->getContext()->getWriteProtection()->allow('WriteProtected');

        $data = [
            'id' => $id->getHex(),
            'relations' => [
                [
                    'id' => $id2->getHex(),
                ],
            ],
        ];

        $this->getWriter()->insert(WriteProtectedDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable_reference`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['wp_id']);
        static::assertEquals($id2->getBytes(), $data[0]['relation_id']);
    }

    public function testWriteTranslationWithoutPermission(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'protected' => 'foobar',
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(WriteProtectedTranslatedDefinition::class, [$data], $context);
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
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();
        $context->getContext()->getWriteProtection()->allow('WriteProtected');

        $data = [
            'id' => $id->getHex(),
            'protected' => 'foobar',
        ];

        $this->getWriter()->insert(WriteProtectedTranslatedDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable_translation`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['wp_id']);
        static::assertEquals('foobar', $data[0]['protected']);
    }

    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext(Defaults::TENANT_ID));

        return $context;
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
