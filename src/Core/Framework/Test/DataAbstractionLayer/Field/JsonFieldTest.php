<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\UnexpectedFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NestedDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;

class JsonFieldTest extends TestCase
{
    use KernelTestBehaviour, CacheTestBehaviour;

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
  `root` longtext NULL,
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

    public function testSearchForNullFields(): void
    {
        $context = $this->createWriteContext();

        $data = [
            ['id' => Uuid::randomHex(), 'data' => null],
            ['id' => Uuid::randomHex(), 'data' => []],
            ['id' => Uuid::randomHex(), 'data' => ['url' => 'foo']],
        ];

        $this->getWriter()->insert(JsonDefinition::class, $data, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('_test_nullable.data', null));
        $result = $this->getRepository()->search(JsonDefinition::class, $criteria, $context->getContext());
        static::assertEquals(1, $result->getTotal());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('_test_nullable.data', '[]'));
        $result = $this->getRepository()->search(JsonDefinition::class, $criteria, $context->getContext());
        static::assertEquals(1, $result->getTotal());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('_test_nullable.data.url', 'foo'));
        $result = $this->getRepository()->search(JsonDefinition::class, $criteria, $context->getContext());
        static::assertEquals(1, $result->getTotal());
    }

    public function testNullableJsonField(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => null,
        ];

        $this->getWriter()->insert(JsonDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertNull($data[0]['data']);
    }

    public function testMissingProperty(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/price/net', $fieldException->getPath());
    }

    public function testMultipleMissingProperties(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['foo' => 'bar', 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(3, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(UnexpectedFieldException::class, \get_class($fieldException));
        static::assertEquals('/price/foo', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[1];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/price/gross', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[2];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/price/net', $fieldException->getPath());
    }

    public function testPropertyTypes(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 'strings are not allowed', 'linked' => false],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/price/net', $fieldException->getPath());
    }

    public function testUnexpectedFieldShouldThrowException(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 13.2, 'linked' => false, 'fail' => 'me'],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(UnexpectedFieldException::class, \get_class($fieldException));
        static::assertEquals('/price/fail', $fieldException->getPath());
    }

    public function testWithoutMappingShouldAcceptAnyKey(): void
    {
        $id = Uuid::randomHex();
        $dt = new \DateTime();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'commit' => ['id' => $id, 'versionId' => $id],
            'entityName' => 'foobar',
            'entityId' => ['id' => $id, 'foo' => 'bar'],
            'action' => 'create',
            'payload' => json_encode(['foo' => 'bar']),
            'createdAt' => $dt,
        ];

        $this->getWriter()->insert(VersionCommitDataDefinition::class, [$data], $context);

        $entityId = $this->connection->fetchColumn('SELECT entity_id FROM version_commit_data WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotEmpty($entityId);

        $entityId = json_decode($entityId, true);

        static::assertEquals(
            $data['entityId'],
            $entityId
        );
    }

    public function testFieldNesting(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => [
                'net' => 15,
                'foo' => [
                    'bar' => false,
                    'baz' => [
                        'deep' => 'invalid',
                    ],
                ],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(NestedDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(3, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/data/gross', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[1];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/data/foo/bar', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[2];
        static::assertEquals(InvalidFieldException::class, \get_class($fieldException));
        static::assertEquals('/data/foo/baz/deep', $fieldException->getPath());
    }

    public function testWriteUtf8(): void
    {
        $context = $this->createWriteContext();

        $data = [
            ['id' => Uuid::randomHex(), 'data' => ['a' => '😄']],
        ];

        $written = $this->getWriter()->insert(JsonDefinition::class, $data, $context);

        static::assertArrayHasKey(JsonDefinition::class, $written);
        static::assertCount(1, $written[JsonDefinition::class]);
        $payload = $written[JsonDefinition::class][0]->getPayload();

        static::assertArrayHasKey('data', $payload);
        static::assertArrayHasKey('a', $payload['data']);
        static::assertEquals('😄', $payload['data']['a']);
    }

    public function testSqlInjectionFails(): void
    {
        $context = $this->createWriteContext();
        $randomKey = Uuid::randomHex();

        $data = [
            ['id' => Uuid::randomHex(), 'data' => [$randomKey => 'bar']],
        ];
        $written = $this->getWriter()->insert(JsonDefinition::class, $data, $context);
        static::assertCount(1, $written[JsonDefinition::class]);

        $context = $context->getContext();

        $taxId = Uuid::randomHex();
        $tax_rate = 15.0;

        $repo = $this->getRepository();
        $criteria = new Criteria();

        $connection = $this->getContainer()->get(Connection::class);
        $insertInjection = sprintf(
            'INSERT INTO `tax` (id, tax_rate, name, created_at) VALUES(UNHEX(%s), %s, "foo", now())',
            $connection->quote($taxId),
            $tax_rate
        );
        $keyWithQuotes = sprintf(
            'data.%s\')) = "%s"); %s; SELECT 1 FROM ((("',
            $randomKey,
            'bar',
            $insertInjection
        );

        $criteria->addFilter(new EqualsFilter($keyWithQuotes, 'bar'));

        // invalid json path
        try {
            $result = $repo->search(JsonDefinition::class, $criteria, $context);
            static::assertEmpty($result->getIds());
        } catch (DBALException $exception) {
            // mysql throws an exception on invalid path
            static::assertTrue(true);
        }
    }

    public function testNestedJsonField(): void
    {
        $context = $this->createWriteContext();
        $insertTime = new \DateTime('2004-02-29 08:59:59.001');
        $updateTime = new \DateTime('2004-02-29 08:59:59.002');

        $id = Uuid::randomHex();

        $insert = [
            'id' => $id,
            'root' => [
                'child' => [
                    'childDate' => $insertTime,
                ],
            ],
        ];
        $written = $this->getWriter()->insert(JsonDefinition::class, [$insert], $context);
        static::assertCount(1, $written);
        static::assertCount(1, $written[JsonDefinition::class]);

        /** @var EntityWriteResult $event */
        $event = $written[JsonDefinition::class][0];
        static::assertEquals($insertTime->format(\DateTime::ATOM), $event->getPayload()['root']['child']['childDate']);

        $update = [
            'id' => $id,
            'root' => [
                'child' => [
                    'childDate' => $updateTime,
                ],
            ],
        ];
        $written = $this->getWriter()->update(JsonDefinition::class, [$update], $context);
        static::assertCount(1, $written);
        static::assertCount(1, $written[JsonDefinition::class]);

        /** @var EntityWriteResult $event */
        $event = $written[JsonDefinition::class][0];
        static::assertEquals($updateTime->format(\DateTime::ATOM), $event->getPayload()['root']['child']['childDate']);
    }

    public function testNestedJsonFilter(): void
    {
        $context = $this->createWriteContext();

        $firstId = Uuid::randomHex();
        $firstDate = new \DateTime('2004-02-29 08:59:59.001');

        $laterId = Uuid::randomHex();
        $laterDate = new \DateTime('2004-02-29 08:59:59.002');

        $latestId = Uuid::randomHex();
        $latestDate = new \DateTime('2005-02-28 08:59:59.000');

        $data = [
            [
                'id' => $firstId,
                'root' => ['child' => ['childDate' => $firstDate]],
            ],
            [
                'id' => $laterId,
                'root' => ['child' => ['childDate' => $laterDate]],
            ],
            [
                'id' => $latestId,
                'root' => ['child' => ['childDate' => $latestDate]],
            ],
        ];
        $this->getWriter()->insert(JsonDefinition::class, $data, $context);

        $repo = $this->getRepository();
        $context = $context->getContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('root.child.childDate', $firstDate->format(Defaults::STORAGE_DATE_FORMAT)));
        $result = $repo->search(JsonDefinition::class, $criteria, $context);

        static::assertCount(1, $result->getIds());
        static::assertEquals([$firstId], $result->getIds());

        $criteria = new Criteria();
        // string match, should only work if its casted correctly
        $criteria->addFilter(new EqualsFilter('root.child.childDate', '2005-02-28 08:59:59'));
        $result = $repo->search(JsonDefinition::class, $criteria, $context);

        static::assertCount(1, $result->getIds());
        static::assertEquals([$latestId], $result->getIds());
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
