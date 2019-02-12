<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AttributesTestDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AttributesFieldTest extends TestCase
{
    use KernelTestBehaviour, CacheTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->exec('DROP TABLE IF EXISTS `attribute_test`');
        $this->connection->exec('
            CREATE TABLE `attribute_test` (
              id BINARY(16) NOT NULL PRIMARY KEY,
              parent_id BINARY(16) NULL,
              name varchar(255) DEFAULT NULL,
              attributes json DEFAULT NULL
        )');

        $this->connection->exec('DROP TABLE IF EXISTS `attribute_test_translation`');
        $this->connection->exec('
            CREATE TABLE `attribute_test_translation` (
              attribute_test_id BINARY(16) NOT NULL,
              language_id BINARY(16) NOT NULL,
              translated_attributes json DEFAULT NULL,
              created_at datetime not null,
              updated_at datetime,              
              PRIMARY KEY (`attribute_test_id`, `language_id`)
        )');

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->exec('DROP TABLE `attribute_test_translation`');
        $this->connection->executeUpdate('DROP TABLE `attribute_test`');
    }

    public function testSearch(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);
        $barId = Uuid::uuid4()->getHex();
        $bazId = Uuid::uuid4()->getHex();
        $entities = [
            [
                'id' => $barId,
                'name' => "foo'bar",
                'attributes' => [
                    'foo' => 'bar',
                ],
            ],
            [
                'id' => $bazId,
                'name' => "foo'bar",
                'attributes' => [
                    'foo' => 'baz',
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $result = $repo->create($entities, Context::createDefaultContext());
        $events = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(2, $events->getPayloads());

        $expected = [$barId, $bazId];
        static::assertEquals($expected, $events->getIds());

        $actual = $repo->search(new Criteria([$barId]), Context::createDefaultContext())->first();
        static::assertEquals($barId, $actual->get('id'));
        static::assertEquals($entities[0]['attributes'], $actual->get('attributes'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('attributes.foo', 'bar'));
        $result = $repo->search($criteria, Context::createDefaultContext());
        $expected = [$barId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('attributes.foo', 'baz'));
        $result = $repo->search($criteria, Context::createDefaultContext());
        $expected = [$bazId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testPatchJson(): void
    {
        $this->addAttributes([
            'foo' => AttributeTypes::STRING,
            'baz' => AttributeTypes::STRING,
        ]);
        $entity = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => "foo'bar",
            'attributes' => [
                'foo' => 'bar',
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($entity['attributes'], $actual->get('attributes'));

        $patch = [
            'id' => $entity['id'],
            'attributes' => [
                'baz' => 'asdf',
            ],
        ];
        $repo->update([$patch], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        $entity = [
            'id' => $entity['id'],
            'attributes' => array_merge_recursive($entity['attributes'], $patch['attributes']),
        ];
        static::assertEquals($entity['attributes'], $actual->get('attributes'));

        $override = [
            'id' => $entity['id'],
            'attributes' => [
                'baz' => 'fdsa',
                'foo' => 'rab',
            ],
        ];

        $repo->update([$override], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($override['attributes'], $actual->get('attributes'));
    }

    public function testPatchObject(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $entity = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => "foo'bar",
            'attributes' => [
                'foo' => 'bar',
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($entity['attributes'], $actual->get('attributes'));

        $patch = [
            'id' => $entity['id'],
            'attributes' => [
                'foo' => [
                    'a' => 1,
                ],
            ],
        ];
        $repo->upsert([$patch], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($patch['attributes'], $actual->get('attributes'));
    }

    public function testPatchEntityAndAttributes(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $entity = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => "foo'bar",
            'attributes' => [
                'foo' => 'bar',
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($entity['attributes'], $actual->get('attributes'));

        $patch = [
            'id' => $entity['id'],
            'name' => "foo'bar'baz",
            'attributes' => [
                'foo' => 'baz',
            ],
        ];
        $result = $repo->upsert([$patch], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getPayloads());
        $expected = $patch;
        static::assertEquals($expected, $event->getPayloads()[0]);

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($patch['name'], $actual->get('name'));
        static::assertEquals($patch['attributes'], $actual->get('attributes'));
    }

    public function testKeyWithDot(): void
    {
        $this->addAttributes(['foo.bar' => AttributeTypes::STRING]);

        $dotId = Uuid::uuid4()->getHex();
        $entities = [
            [
                'id' => $dotId,
                'name' => "foo'bar",
                'attributes' => [
                    'foo.bar' => 'baz',
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('attributes."foo.bar"', 'baz'));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertEquals([$dotId], array_values($result->getIds()));
    }

    public function testSortingInt(): void
    {
        $this->addAttributes(['int' => AttributeTypes::INT]);
        $smallId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'attributes' => [
                    'int' => 2,
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'attributes' => [
                    'int' => 10,
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.int', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(10, $first->get('attributes')['int']);
        static::assertEquals(2, $last->get('attributes')['int']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.int', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(2, $first->get('attributes')['int']);
        static::assertEquals(10, $last->get('attributes')['int']);
    }

    public function testSortingFloat(): void
    {
        $this->addAttributes(['float' => AttributeTypes::FLOAT]);

        $smallId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'attributes' => [
                    'float' => 2.0,
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'attributes' => [
                    'float' => 10.0,
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.float', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(10.0, $first->get('attributes')['float']);
        static::assertEquals(2.0, $last->get('attributes')['float']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.float', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(2.0, $first->get('attributes')['float']);
        static::assertEquals(10.0, $last->get('attributes')['float']);
    }

    public function testSortingDate(): void
    {
        $this->addAttributes(['datetime' => AttributeTypes::DATETIME]);

        $smallId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();

        $earlierDate = new \DateTime('1990-01-01');
        $laterDate = new \DateTime('1990-01-02');

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'attributes' => [
                    'datetime' => $earlierDate,
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'attributes' => [
                    'datetime' => $laterDate,
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.datetime', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();

        static::assertEquals($laterDate->format(Defaults::DATE_FORMAT), $first->get('attributes')['datetime']);
        static::assertEquals($earlierDate->format(Defaults::DATE_FORMAT), $last->get('attributes')['datetime']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.datetime', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals($earlierDate->format(Defaults::DATE_FORMAT), $first->get('attributes')['datetime']);
        static::assertEquals($laterDate->format(Defaults::DATE_FORMAT), $last->get('attributes')['datetime']);
    }

    public function testSortingDateTime(): void
    {
        $this->addAttributes(['datetime' => AttributeTypes::DATETIME]);

        $ids = [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()];
        $dateTimes = [
            new \DateTime('1990-01-01'),
            new \DateTime('1990-01-01T00:01'),
            new \DateTime('1990-01-01T12:00'),
            new \DateTime('1990-01-02'),
        ];

        $entities = [];
        foreach ($ids as $i => $id) {
            $entities[] = [
                'id' => $id,
                'name' => $id,
                'attributes' => [
                    'datetime' => $dateTimes[$i],
                ],
            ];
        }

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.datetime', FieldSorting::DESCENDING));
        $result = array_values($repo->search($criteria, Context::createDefaultContext())->getElements());
        static::assertCount(4, $result);

        static::assertEquals($dateTimes[3]->format(Defaults::DATE_FORMAT), $result[0]->get('attributes')['datetime']);
        static::assertEquals($dateTimes[2]->format(Defaults::DATE_FORMAT), $result[1]->get('attributes')['datetime']);
        static::assertEquals($dateTimes[1]->format(Defaults::DATE_FORMAT), $result[2]->get('attributes')['datetime']);
        static::assertEquals($dateTimes[0]->format(Defaults::DATE_FORMAT), $result[3]->get('attributes')['datetime']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.datetime', FieldSorting::ASCENDING));
        $result = array_values($repo->search($criteria, Context::createDefaultContext())->getElements());
        static::assertCount(4, $result);

        static::assertEquals($dateTimes[0]->format(Defaults::DATE_FORMAT), $result[0]->get('attributes')['datetime']);
        static::assertEquals($dateTimes[1]->format(Defaults::DATE_FORMAT), $result[1]->get('attributes')['datetime']);
        static::assertEquals($dateTimes[2]->format(Defaults::DATE_FORMAT), $result[2]->get('attributes')['datetime']);
        static::assertEquals($dateTimes[3]->format(Defaults::DATE_FORMAT), $result[3]->get('attributes')['datetime']);
    }

    public function testSortingString(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $smallId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'attributes' => [
                    'foo' => 'a',
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'attributes' => [
                    'foo' => 'ab',
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.foo', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals('ab', $first->get('attributes')['foo']);
        static::assertEquals('a', $last->get('attributes')['foo']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('attributes.foo', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals('a', $first->get('attributes')['foo']);
        static::assertEquals('ab', $last->get('attributes')['foo']);
    }

    public function testStringEqualsCriteria(): void
    {
        $this->addAttributes(['string' => AttributeTypes::STRING]);

        $aId = Uuid::uuid4()->getHex();
        $upperAId = Uuid::uuid4()->getHex();
        $emptyStringId = Uuid::uuid4()->getHex();

        $entities = [
            ['id' => $aId, 'attributes' => ['string' => 'a']],
            ['id' => $upperAId, 'attributes' => ['string' => 'A']],

            ['id' => $emptyStringId, 'attributes' => ['string' => '']],

            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['string' => false]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['string' => null]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => []],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.string', 'a'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$aId, $upperAId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.string', 'A'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$aId, $upperAId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.string', ''));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$emptyStringId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testBooleanEqualsCriteria(): void
    {
        $this->addAttributes(['bool' => AttributeTypes::BOOL]);
        $trueId = Uuid::uuid4()->getHex();
        $trueIntId = Uuid::uuid4()->getHex();

        $falseId = Uuid::uuid4()->getHex();
        $falseIntId = Uuid::uuid4()->getHex();

        $nullId = Uuid::uuid4()->getHex();
        $undefinedId = Uuid::uuid4()->getHex();

        $entities = [
            ['id' => $trueId, 'attributes' => ['bool' => true]],
            ['id' => $trueIntId, 'attributes' => ['bool' => 1]],
            ['id' => $falseId, 'attributes' => ['bool' => false]],
            ['id' => $falseIntId, 'attributes' => ['bool' => 0]],
            ['id' => $nullId, 'attributes' => ['bool' => null]],
            ['id' => $undefinedId, 'attributes' => []],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.bool', false));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$falseId, $falseIntId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaTrue = new Criteria();
        $criteriaTrue->addFilter(new EqualsFilter('attributes.bool', true));
        $result = $repo->search($criteriaTrue, Context::createDefaultContext());
        $expected = [$trueId, $trueIntId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaTrue = new Criteria();
        $criteriaTrue->addFilter(new EqualsFilter('attributes.bool', null));
        $result = $repo->search($criteriaTrue, Context::createDefaultContext());
        $expected = [$undefinedId, $nullId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testIntEqualsCriteria(): void
    {
        $this->addAttributes(['int' => AttributeTypes::INT]);

        $intId = Uuid::uuid4()->getHex();
        $floatId = Uuid::uuid4()->getHex();
        $zeroIntId = Uuid::uuid4()->getHex();
        $zeroFloatId = Uuid::uuid4()->getHex();
        $falseId = Uuid::uuid4()->getHex();

        $entities = [
            ['id' => $intId, 'attributes' => ['int' => 10]],
            ['id' => $floatId, 'attributes' => ['int' => 10.0]],

            ['id' => $zeroIntId, 'attributes' => ['int' => 0]],
            ['id' => $zeroFloatId, 'attributes' => ['int' => 0.0]],

            ['id' => $falseId, 'attributes' => ['int' => false]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['int' => null]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => []],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.int', 10));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$intId, $floatId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.int', 10.0));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$intId, $floatId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.int', 0));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$zeroIntId, $zeroFloatId, $falseId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.int', 0.0));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$zeroIntId, $zeroFloatId, $falseId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testFloatEqualsCriteria(): void
    {
        $this->addAttributes(['float' => AttributeTypes::FLOAT]);

        $dotOneId = Uuid::uuid4()->getHex();
        $almostDotOneId = Uuid::uuid4()->getHex();

        $entities = [
            ['id' => $dotOneId, 'attributes' => ['float' => 0.1]],
            ['id' => $almostDotOneId, 'attributes' => ['float' => 0.099999999999999]],

            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['float' => 0]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['float' => 0.0]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['float' => 1]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.float', 0.1));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$dotOneId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.float', 0.099999999999999));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$almostDotOneId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testDateTimeEqualsCriteria(): void
    {
        $this->addAttributes(['datetime' => AttributeTypes::DATETIME, 'float' => AttributeTypes::FLOAT]);

        $ids = [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()];
        $nowId = Uuid::uuid4()->getHex();
        $now = (new \DateTime())->format(Defaults::DATE_FORMAT);

        $entities = [
            ['id' => $ids[0], 'attributes' => ['datetime' => new \DateTime('1990-01-01')]],
            ['id' => $ids[1], 'attributes' => ['datetime' => new \DateTime('1990-01-01T00:00')]],
            ['id' => $ids[2], 'attributes' => ['datetime' => new \DateTime('1990-01-01T00:00:00')]],
            ['id' => $ids[3], 'attributes' => ['datetime' => new \DateTime('1990-01-01T00:00:00.000000')]],

            ['id' => $nowId, 'attributes' => ['datetime' => $now]],

            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['datetime' => null]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['datetime' => false]],
            ['id' => Uuid::uuid4()->getHex(), 'attributes' => ['float' => 0.0]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.datetime', '1990-01-01'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = $ids;
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('attributes.datetime', '1990-01-01T00:00:00.000000'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = $ids;
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaNow = new Criteria();
        $criteriaNow->addFilter(new EqualsFilter('attributes.datetime', $now));
        $result = $repo->search($criteriaNow, Context::createDefaultContext());
        $expected = [$nowId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testSetAttributesOnNullColumn(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $id = Uuid::uuid4()->getHex();
        $entity = ['id' => $id, 'attributes' => null];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'attributes' => [
                'foo' => 'bar',
            ],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getPayloads());
        $expected = $update;
        static::assertEquals($expected, $event->getPayloads()[0]);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals($update['attributes'], $first->get('attributes'));
    }

    public function testSetAttributesOnEmptyArray(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $id = Uuid::uuid4()->getHex();
        $entity = ['id' => $id, 'attributes' => []];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'attributes' => [
                'foo' => 'bar',
            ],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getPayloads());
        $expected = ['id' => $id, 'attributes' => $update['attributes']];
        static::assertEquals($expected, $event->getPayloads()[0]);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals($update['attributes'], $first->get('attributes'));
    }

    public function testUpdateAttributeWithDot(): void
    {
        $this->addAttributes(['foo.bar' => AttributeTypes::STRING]);

        $id = Uuid::uuid4()->getHex();
        $entity = ['id' => $id, 'attributes' => []];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'attributes' => [
                'foo.bar' => 'foo dot bar',
            ],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getPayloads());
        $expected = $update;
        $expected['attributes'] = $update['attributes'];
        static::assertEquals($expected, $event->getPayloads()[0]);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals($update['attributes'], $first->get('attributes'));
    }

    public function testSetAttributesToNull(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $id = Uuid::uuid4()->getHex();
        $entity = ['id' => $id, 'attributes' => ['foo' => 'bar']];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = ['id' => $id, 'attributes' => null];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getPayloads());
        static::assertEquals($update, $event->getPayloads()[0]);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertNull($first->get('attributes'));
    }

    public function testSetAttributesToEmptyArrayIsNull(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $id = Uuid::uuid4()->getHex();
        $entity = ['id' => $id, 'attributes' => ['foo' => 'bar']];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = ['id' => $id, 'attributes' => []];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getPayloads());
        static::assertEquals(['id' => $id, 'attributes' => null], $event->getPayloads()[0]);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertNull($first->get('attributes'));
    }

    public function testInheritance(): void
    {
        $this->addAttributes(['foo' => AttributeTypes::STRING]);

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();

        $repo = $this->getTestRepository();

        $entities = [
            ['id' => $parentId, 'name' => 'parent', 'attributes' => ['foo' => 'bar']],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ];
        $repo->create($entities, Context::createDefaultContext());

        /** @var ArrayEntity $parent */
        $parent = $repo->search(new Criteria([$parentId]), Context::createDefaultContext())->first();
        static::assertNotNull($parent);

        static::assertEquals('parent', $parent->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->get('attributes'));
        static::assertEquals('parent', $parent->getViewData()->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->getViewData()->get('attributes'));

        /** @var ArrayEntity $child */
        $child = $repo->search(new Criteria([$childId]), Context::createDefaultContext())->first();
        static::assertNotNull($child);

        static::assertEquals('child', $child->get('name'));
        static::assertNull($child->get('attributes'));
        static::assertEquals('child', $child->getViewData()->get('name'));
        static::assertEquals(['foo' => 'bar'], $child->getViewData()->get('attributes'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('attributes.foo', 'bar'));

        $results = $repo->search($criteria, Context::createDefaultContext());
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $results->getIds());
    }

    public function testInheritanceAttributesAreMerged(): void
    {
        $this->addAttributes([
            'foo' => AttributeTypes::STRING,
            'child' => AttributeTypes::STRING,
        ]);

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();

        $repo = $this->getTestRepository();

        $entities = [
            ['id' => $parentId, 'name' => 'parent', 'attributes' => ['foo' => 'bar']],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId, 'attributes' => ['child' => 'value']],
        ];
        $repo->create($entities, Context::createDefaultContext());

        /** @var ArrayEntity $parent */
        $parent = $repo->search(new Criteria([$parentId]), Context::createDefaultContext())->first();
        static::assertNotNull($parent);

        static::assertEquals('parent', $parent->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->get('attributes'));
        static::assertEquals('parent', $parent->getViewData()->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->getViewData()->get('attributes'));

        /** @var ArrayEntity $child */
        $child = $repo->search(new Criteria([$childId]), Context::createDefaultContext())->first();
        static::assertNotNull($child);

        static::assertEquals('child', $child->get('name'));
        static::assertEquals(['child' => 'value'], $child->get('attributes'));

        static::assertEquals('child', $child->getViewData()->get('name'));
        static::assertEquals(['foo' => 'bar', 'child' => 'value'], $child->getViewData()->get('attributes'));
    }

    private function addAttributes(array $attributeTypes): void
    {
        $attributeRepo = $this->getContainer()->get('attribute.repository');

        $attributes = [];
        foreach ($attributeTypes as $name => $type) {
            $attributes[] = ['id' => Uuid::uuid4()->getHex(), 'name' => $name, 'type' => $type];
        }
        $attributeRepo->create($attributes, Context::createDefaultContext());
    }

    private function getTestRepository(): EntityRepository
    {
        return new EntityRepository(
          AttributesTestDefinition::class,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get(EventDispatcherInterface::class)
        );
    }
}
