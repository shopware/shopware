<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\ArrayParameters\Exception\MissingNamedParameter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NestedDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\EmptyEntityExistence;

/**
 * @internal
 */
class JsonFieldTest extends TestCase
{
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS `_test_nullable`;
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4,
  `root` longtext CHARACTER SET utf8mb4,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

EOF;
        $this->connection->executeStatement($nullableTable);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `_test_nullable`');
    }

    public function testSearchForNullFields(): void
    {
        $context = $this->createWriteContext();

        $data = [
            ['id' => Uuid::randomHex(), 'data' => null],
            ['id' => Uuid::randomHex(), 'data' => []],
            ['id' => Uuid::randomHex(), 'data' => ['url' => 'foo']],
        ];

        $this->getWriter()->insert($this->registerDefinition(JsonDefinition::class), $data, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('_test_nullable.data', null));
        $result = $this->getRepository()->search($this->registerDefinition(JsonDefinition::class), $criteria, $context->getContext());
        static::assertSame(1, $result->getTotal());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('_test_nullable.data', '[]'));
        $result = $this->getRepository()->search($this->registerDefinition(JsonDefinition::class), $criteria, $context->getContext());
        static::assertSame(1, $result->getTotal());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('_test_nullable.data.url', 'foo'));
        $result = $this->getRepository()->search($this->registerDefinition(JsonDefinition::class), $criteria, $context->getContext());
        static::assertSame(1, $result->getTotal());
    }

    public function testNullableJsonField(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => null,
        ];

        $this->getWriter()->insert($this->registerDefinition(JsonDefinition::class), [$data], $context);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertSame(Uuid::fromHexToBytes($id), $data[0]['id']);
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
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;

        try {
            $this->getWriter()->insert($this->registerDefinition(ProductDefinition::class), [$data], $context);
        } catch (WriteException $ex) {
        }

        static::assertInstanceOf(WriteException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];

        static::assertSame(WriteConstraintViolationException::class, $fieldException::class);
        static::assertSame('/0/price', $fieldException->getPath());
        static::assertSame('/0/net', $fieldException->getViolations()->get(0)->getPropertyPath());
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
            'price' => [
                ['foo' => 'bar', 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;

        try {
            $this->getWriter()->insert($this->registerDefinition(ProductDefinition::class), [$data], $context);
        } catch (WriteException $ex) {
        }

        static::assertInstanceOf(WriteException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldException);

        $violations = $fieldException->getViolations();

        $violation = $violations->get(0);
        static::assertSame('/0/currencyId', $violation->getPropertyPath());

        $violation = $violations->get(1);
        static::assertSame('/0/gross', $violation->getPropertyPath());

        $violation = $violations->get(2);
        static::assertSame('/0/net', $violation->getPropertyPath());
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
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 'strings are not allowed', 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $ex = null;

        try {
            $this->getWriter()->insert($this->registerDefinition(ProductDefinition::class), [$data], $context);
        } catch (WriteException $ex) {
        }

        static::assertInstanceOf(WriteException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertSame(WriteConstraintViolationException::class, $fieldException::class);
        static::assertSame('/0/price', $fieldException->getPath());
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

        $this->getWriter()->insert($this->registerDefinition(VersionCommitDataDefinition::class), [$data], $context);

        $entityId = $this->connection->fetchOne('SELECT entity_id FROM version_commit_data WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotEmpty($entityId);

        $entityId = json_decode((string) $entityId, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
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
            $this->getWriter()->insert($this->registerDefinition(NestedDefinition::class), [$data], $context);
        } catch (WriteException $ex) {
        }

        static::assertInstanceOf(WriteException::class, $ex);
        static::assertCount(3, $ex->getExceptions());

        $fieldExceptionOne = $ex->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldExceptionOne);
        static::assertSame('/0/data', $fieldExceptionOne->getPath());
        static::assertSame('/gross', $fieldExceptionOne->getViolations()->get(0)->getPropertyPath());

        $fieldExceptionTwo = $ex->getExceptions()[1];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldExceptionTwo);
        static::assertSame('/0/data/foo', $fieldExceptionTwo->getPath());
        static::assertSame('/bar', $fieldExceptionTwo->getViolations()->get(0)->getPropertyPath());

        $fieldExceptionThree = $ex->getExceptions()[2];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldExceptionThree);
        static::assertSame('/0/data/foo/baz', $fieldExceptionThree->getPath());
        static::assertSame('/deep', $fieldExceptionThree->getViolations()->get(0)->getPropertyPath());
    }

    public function testWriteUtf8(): void
    {
        $context = $this->createWriteContext();

        $data = [
            ['id' => Uuid::randomHex(), 'data' => ['a' => '😄']],
        ];

        $written = $this->getWriter()->insert($this->registerDefinition(JsonDefinition::class), $data, $context);

        static::assertArrayHasKey(JsonDefinition::ENTITY_NAME, $written);
        static::assertCount(1, $written[JsonDefinition::ENTITY_NAME]);
        $payload = $written[JsonDefinition::ENTITY_NAME][0]->getPayload();

        static::assertArrayHasKey('data', $payload);
        static::assertArrayHasKey('a', $payload['data']);
        static::assertSame('😄', $payload['data']['a']);
    }

    public function testSqlInjectionFails(): void
    {
        $context = $this->createWriteContext();
        $randomKey = Uuid::randomHex();

        $data = [
            ['id' => Uuid::randomHex(), 'data' => [$randomKey => 'bar']],
        ];
        $written = $this->getWriter()->insert($this->registerDefinition(JsonDefinition::class), $data, $context);
        static::assertCount(1, $written[JsonDefinition::ENTITY_NAME]);

        $context = $context->getContext();

        $taxId = Uuid::randomHex();
        $taxRate = 15.0;

        $repo = $this->getRepository();
        $criteria = new Criteria();

        $connection = $this->getContainer()->get(Connection::class);
        $insertInjection = sprintf(
            'INSERT INTO `tax` (id, tax_rate, name, created_at) VALUES(UNHEX(%s), %s, "foo", %s)',
            (string) $connection->quote($taxId),
            (string) $taxRate, // use php string conversion, to avoid locale based float to string conversion in sprintf
            (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
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
            $repo->search($this->registerDefinition(JsonDefinition::class), $criteria, $context);
        } catch (\Throwable $e) {
            if ($e instanceof DriverException) {
                static::assertStringContainsString('Invalid JSON path expression', $e->getMessage());
            }
            if ($e instanceof MissingNamedParameter) {
                static::assertStringContainsString('Named parameter ', $e->getMessage());
            }
        }
    }

    public function testNestedJsonField(): void
    {
        $insertTime = new \DateTime('2004-02-29 08:59:59.001');

        $serializer = $this->getContainer()->get(JsonFieldSerializer::class);

        $field = new JsonField('root', 'root', [
            new JsonField('child', 'child', [
                new DateTimeField('childDateTime', 'childDateTime'),
                new DateField('childDate', 'childDate'),
            ]),
        ]);

        $value = [
            'child' => [
                'childDateTime' => $insertTime,
                'childDate' => $insertTime,
            ],
        ];

        $payload = $serializer->encode(
            $field,
            new EmptyEntityExistence(),
            new KeyValuePair('root', $value, true),
            new WriteParameterBag(
                $this->createMock(EntityDefinition::class),
                WriteContext::createFromContext(Context::createDefaultContext()),
                '',
                new WriteCommandQueue()
            )
        );

        // assert is generator
        static::assertIsIterable($payload);

        $payload = iterator_to_array($payload);

        static::assertArrayHasKey('root', $payload);
        static::assertIsString($payload['root']);

        $decoded = json_decode($payload['root'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('child', $decoded);
        static::assertArrayHasKey('childDateTime', $decoded['child']);

        static::assertEquals($insertTime->format(Defaults::STORAGE_DATE_TIME_FORMAT), $decoded['child']['childDateTime']);
        static::assertEquals($insertTime->format(Defaults::STORAGE_DATE_FORMAT), $decoded['child']['childDate']);
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
                'root' => ['child' => ['childDateTime' => $firstDate, 'childDate' => $firstDate]],
            ],
            [
                'id' => $laterId,
                'root' => ['child' => ['childDateTime' => $laterDate, 'childDate' => $laterDate]],
            ],
            [
                'id' => $latestId,
                'root' => ['child' => ['childDateTime' => $latestDate, 'childDate' => $latestDate]],
            ],
        ];
        $this->getWriter()->insert($this->registerDefinition(JsonDefinition::class), $data, $context);

        $repo = $this->getRepository();
        $context = $context->getContext();

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('root.child.childDateTime', $firstDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)),
                new EqualsFilter('root.child.childDate', $firstDate->format(Defaults::STORAGE_DATE_FORMAT)),
            ]
        ));
        $result = $repo->search($this->registerDefinition(JsonDefinition::class), $criteria, $context);

        static::assertCount(1, $result->getIds());
        static::assertSame([$firstId], $result->getIds());

        $criteria = new Criteria();
        // string match, should only work if its casted correctly
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('root.child.childDateTime', '2005-02-28 08:59:59'),
                new EqualsFilter('root.child.childDate', '2005-02-28'),
            ]
        ));
        $result = $repo->search($this->registerDefinition(JsonDefinition::class), $criteria, $context);

        static::assertCount(1, $result->getIds());
        static::assertSame([$latestId], $result->getIds());
    }

    protected function createWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
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
