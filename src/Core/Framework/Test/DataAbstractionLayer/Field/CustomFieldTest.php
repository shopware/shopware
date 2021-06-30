<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomFieldTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

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
              custom json DEFAULT NULL,
              created_at DATETIME(3) NOT NULL,
              updated_at DATETIME(3) NULL
        )');

        $this->connection->exec('DROP TABLE IF EXISTS `attribute_test_translation`');
        $this->connection->exec('
            CREATE TABLE `attribute_test_translation` (
              attribute_test_id BINARY(16) NOT NULL,
              language_id BINARY(16) NOT NULL,
              custom_translated json DEFAULT NULL,
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
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);
        $barId = Uuid::randomHex();
        $bazId = Uuid::randomHex();
        $entities = [
            [
                'id' => $barId,
                'name' => "foo'bar",
                'custom' => [
                    'foo' => 'bar',
                ],
            ],
            [
                'id' => $bazId,
                'name' => "foo'bar",
                'custom' => [
                    'foo' => 'baz',
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $result = $repo->create($entities, Context::createDefaultContext());
        $events = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(2, $events->getPayloads());

        $expected = [$barId, $bazId];
        static::assertEquals($expected, $events->getIds());

        $actual = $repo->search(new Criteria([$barId]), Context::createDefaultContext())->first();
        static::assertEquals($barId, $actual->get('id'));
        static::assertEquals($entities[0]['custom'], $actual->get('custom'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom.foo', 'bar'));
        $result = $repo->search($criteria, Context::createDefaultContext());
        $expected = [$barId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom.foo', 'baz'));
        $result = $repo->search($criteria, Context::createDefaultContext());
        $expected = [$bazId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testPatchJson(): void
    {
        $this->addCustomFields([
            'foo' => CustomFieldTypes::TEXT,
            'baz' => CustomFieldTypes::TEXT,
        ]);
        $entity = [
            'id' => Uuid::randomHex(),
            'name' => "foo'bar",
            'custom' => [
                'foo' => 'bar',
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($entity['custom'], $actual->get('custom'));

        $patch = [
            'id' => $entity['id'],
            'custom' => [
                'baz' => 'asdf',
            ],
        ];
        $repo->update([$patch], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        $entity = [
            'id' => $entity['id'],
            'custom' => array_merge_recursive($entity['custom'], $patch['custom']),
        ];
        static::assertEquals($entity['custom'], $actual->get('custom'));

        $override = [
            'id' => $entity['id'],
            'custom' => [
                'baz' => 'fdsa',
                'foo' => 'rab',
            ],
        ];

        $repo->update([$override], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($override['custom'], $actual->get('custom'));
    }

    public function testPatchObject(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::JSON]);

        $entity = [
            'id' => Uuid::randomHex(),
            'name' => "foo'bar",
            'custom' => [
                'foo' => ['bar'],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($entity['custom'], $actual->get('custom'));

        $patch = [
            'id' => $entity['id'],
            'custom' => [
                'foo' => [
                    'a' => 1,
                ],
            ],
        ];
        $repo->upsert([$patch], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($patch['custom'], $actual->get('custom'));
    }

    public function testPatchEntityAndCustomFields(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $entity = [
            'id' => Uuid::randomHex(),
            'name' => "foo'bar",
            'custom' => [
                'foo' => 'bar',
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($entity['custom'], $actual->get('custom'));

        $patch = [
            'id' => $entity['id'],
            'name' => "foo'bar'baz",
            'custom' => [
                'foo' => 'baz',
            ],
        ];
        $result = $repo->upsert([$patch], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());
        $expected = $patch;
        $payload = $event->getPayloads()[0];
        unset($payload['updatedAt']);

        static::assertEquals($expected, $payload);

        $actual = $repo->search(new Criteria([$entity['id']]), Context::createDefaultContext())->first();
        static::assertEquals($patch['name'], $actual->get('name'));
        static::assertEquals($patch['custom'], $actual->get('custom'));
    }

    public function testKeyWithDot(): void
    {
        $this->addCustomFields(['foo.bar' => CustomFieldTypes::TEXT]);

        $dotId = Uuid::randomHex();
        $entities = [
            [
                'id' => $dotId,
                'name' => "foo'bar",
                'custom' => [
                    'foo.bar' => 'baz',
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom."foo.bar"', 'baz'));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertEquals([$dotId], array_values($result->getIds()));
    }

    public function testSortingHyphenatedJson(): void
    {
        $this->addCustomFields(['hyphenated-property' => CustomFieldTypes::JSON]);

        $entities = [
            [
                'id' => Uuid::randomHex(),
                'name' => 'foo',
                'custom' => [
                    'hyphenated-property' => [
                        'hyphenated-child' => 'bar',
                    ],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'bar',
                'custom' => [
                    'hyphenated-property' => [
                        'hyphenated-child' => 'foo',
                    ],
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.hyphenated-property.hyphenated-child', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals('foo', $first->get('custom')['hyphenated-property']['hyphenated-child']);
        static::assertEquals('bar', $last->get('custom')['hyphenated-property']['hyphenated-child']);
    }

    public function testSortingInt(): void
    {
        $this->addCustomFields(['int' => CustomFieldTypes::INT]);
        $smallId = Uuid::randomHex();
        $bigId = Uuid::randomHex();

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'custom' => [
                    'int' => 2,
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'custom' => [
                    'int' => 10,
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.int', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(10, $first->get('custom')['int']);
        static::assertEquals(2, $last->get('custom')['int']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.int', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(2, $first->get('custom')['int']);
        static::assertEquals(10, $last->get('custom')['int']);
    }

    public function testSortingFloat(): void
    {
        $this->addCustomFields(['float' => CustomFieldTypes::FLOAT]);

        $smallId = Uuid::randomHex();
        $bigId = Uuid::randomHex();

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'custom' => [
                    'float' => 2.0,
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'custom' => [
                    'float' => 10.0,
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.float', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(10.0, $first->get('custom')['float']);
        static::assertEquals(2.0, $last->get('custom')['float']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.float', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals(2.0, $first->get('custom')['float']);
        static::assertEquals(10.0, $last->get('custom')['float']);
    }

    public function testSortingDate(): void
    {
        $this->addCustomFields(['datetime' => CustomFieldTypes::DATETIME]);

        $smallId = Uuid::randomHex();
        $bigId = Uuid::randomHex();

        $earlierDate = new \DateTime('1990-01-01');
        $laterDate = new \DateTime('1990-01-02');

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'custom' => [
                    'datetime' => $earlierDate,
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'custom' => [
                    'datetime' => $laterDate,
                ],
            ],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.datetime', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();

        static::assertEquals($laterDate->format(\DateTime::ATOM), $first->get('custom')['datetime']);
        static::assertEquals($earlierDate->format(\DateTime::ATOM), $last->get('custom')['datetime']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.datetime', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals($earlierDate->format(\DateTime::ATOM), $first->get('custom')['datetime']);
        static::assertEquals($laterDate->format(\DateTime::ATOM), $last->get('custom')['datetime']);
    }

    public function testSortingDateTime(): void
    {
        $this->addCustomFields(['datetime' => CustomFieldTypes::DATETIME]);

        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        /** @var \DateTimeInterface[] $dateTimes */
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
                'custom' => [
                    'datetime' => $dateTimes[$i],
                ],
            ];
        }

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.datetime', FieldSorting::DESCENDING));
        $result = array_values($repo->search($criteria, Context::createDefaultContext())->getElements());
        static::assertCount(4, $result);

        static::assertEquals($dateTimes[3]->format(\DateTime::ATOM), $result[0]->get('custom')['datetime']);
        static::assertEquals($dateTimes[2]->format(\DateTime::ATOM), $result[1]->get('custom')['datetime']);
        static::assertEquals($dateTimes[1]->format(\DateTime::ATOM), $result[2]->get('custom')['datetime']);
        static::assertEquals($dateTimes[0]->format(\DateTime::ATOM), $result[3]->get('custom')['datetime']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.datetime', FieldSorting::ASCENDING));
        $result = array_values($repo->search($criteria, Context::createDefaultContext())->getElements());
        static::assertCount(4, $result);

        static::assertEquals($dateTimes[0]->format(\DateTime::ATOM), $result[0]->get('custom')['datetime']);
        static::assertEquals($dateTimes[1]->format(\DateTime::ATOM), $result[1]->get('custom')['datetime']);
        static::assertEquals($dateTimes[2]->format(\DateTime::ATOM), $result[2]->get('custom')['datetime']);
        static::assertEquals($dateTimes[3]->format(\DateTime::ATOM), $result[3]->get('custom')['datetime']);
    }

    public function testSortingString(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $smallId = Uuid::randomHex();
        $bigId = Uuid::randomHex();

        $entities = [
            [
                'id' => $smallId,
                'name' => "foo'bar",
                'custom' => [
                    'foo' => 'a',
                ],
            ],
            [
                'id' => $bigId,
                'name' => "foo'bar",
                'custom' => [
                    'foo' => 'ab',
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.foo', FieldSorting::DESCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals('ab', $first->get('custom')['foo']);
        static::assertEquals('a', $last->get('custom')['foo']);

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('custom.foo', FieldSorting::ASCENDING));
        $result = $repo->search($criteria, Context::createDefaultContext());
        static::assertCount(2, $result);

        $first = $result->first();
        $last = $result->last();
        static::assertEquals('a', $first->get('custom')['foo']);
        static::assertEquals('ab', $last->get('custom')['foo']);
    }

    public function testStringEqualsCriteria(): void
    {
        $this->addCustomFields(['string' => CustomFieldTypes::TEXT]);

        $aId = Uuid::randomHex();
        $upperAId = Uuid::randomHex();

        $entities = [
            ['id' => $aId, 'custom' => ['string' => 'a']],
            ['id' => $upperAId, 'custom' => ['string' => 'A']],

            ['id' => Uuid::randomHex(), 'custom' => ['string' => null]],
            ['id' => Uuid::randomHex(), 'custom' => []],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.string', 'a'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$aId, $upperAId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.string', 'A'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$aId, $upperAId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testBooleanEqualsCriteria(): void
    {
        $this->addCustomFields(['bool' => CustomFieldTypes::BOOL]);
        $trueId = Uuid::randomHex();
        $falseId = Uuid::randomHex();

        $nullId = Uuid::randomHex();
        $undefinedId = Uuid::randomHex();

        $entities = [
            ['id' => $trueId, 'custom' => ['bool' => true]],
            ['id' => $falseId, 'custom' => ['bool' => false]],
            ['id' => $nullId, 'custom' => ['bool' => null]],
            ['id' => $undefinedId, 'custom' => []],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.bool', false));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$falseId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaTrue = new Criteria();
        $criteriaTrue->addFilter(new EqualsFilter('custom.bool', true));
        $result = $repo->search($criteriaTrue, Context::createDefaultContext());
        $expected = [$trueId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaTrue = new Criteria();
        $criteriaTrue->addFilter(new EqualsFilter('custom.bool', null));
        $result = $repo->search($criteriaTrue, Context::createDefaultContext());
        $expected = [$undefinedId, $nullId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testIntEqualsCriteria(): void
    {
        $this->addCustomFields(['int' => CustomFieldTypes::INT]);

        $intId = Uuid::randomHex();
        $zeroIntId = Uuid::randomHex();

        $entities = [
            ['id' => $intId, 'custom' => ['int' => 10]],

            ['id' => $zeroIntId, 'custom' => ['int' => 0]],

            ['id' => Uuid::randomHex(), 'custom' => ['int' => null]],
            ['id' => Uuid::randomHex(), 'custom' => []],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.int', 10));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$intId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.int', 10.0));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$intId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.int', 0));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$zeroIntId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testFloatEqualsCriteria(): void
    {
        $this->addCustomFields(['float' => CustomFieldTypes::FLOAT]);

        $dotOneId = Uuid::randomHex();
        $almostDotOneId = Uuid::randomHex();

        $entities = [
            ['id' => $dotOneId, 'custom' => ['float' => 0.1]],
            ['id' => $almostDotOneId, 'custom' => ['float' => 0.099999999999999]],

            ['id' => Uuid::randomHex(), 'custom' => ['float' => 0]],
            ['id' => Uuid::randomHex(), 'custom' => ['float' => 0.0]],
            ['id' => Uuid::randomHex(), 'custom' => ['float' => 1]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.float', 0.1));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$dotOneId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.float', 0.099999999999999));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = [$almostDotOneId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testDateTimeEqualsCriteria(): void
    {
        $this->addCustomFields(['datetime' => CustomFieldTypes::DATETIME, 'float' => CustomFieldTypes::FLOAT]);

        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $nowId = Uuid::randomHex();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $entities = [
            ['id' => $ids[0], 'custom' => ['datetime' => new \DateTime('1990-01-01')]],
            ['id' => $ids[1], 'custom' => ['datetime' => new \DateTime('1990-01-01T00:00')]],
            ['id' => $ids[2], 'custom' => ['datetime' => new \DateTime('1990-01-01T00:00:00')]],
            ['id' => $ids[3], 'custom' => ['datetime' => new \DateTime('1990-01-01T00:00:00.000000')]],

            ['id' => $nowId, 'custom' => ['datetime' => $now]],

            ['id' => Uuid::randomHex(), 'custom' => ['datetime' => null]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.datetime', '1990-01-01'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = $ids;
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaFalse = new Criteria();
        $criteriaFalse->addFilter(new EqualsFilter('custom.datetime', '1990-01-01T00:00:00.000000'));
        $result = $repo->search($criteriaFalse, Context::createDefaultContext());
        $expected = $ids;
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteriaNow = new Criteria();
        $criteriaNow->addFilter(new EqualsFilter('custom.datetime', $now));
        $result = $repo->search($criteriaNow, Context::createDefaultContext());
        $expected = [$nowId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());
    }

    public function testSetCustomFieldsOnNullColumn(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $id = Uuid::randomHex();
        $entity = ['id' => $id, 'custom' => null];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'custom' => [
                'foo' => 'bar',
            ],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());
        $expected = $update;
        $payload = $event->getPayloads()[0];
        unset($payload['updatedAt']);

        static::assertEquals($expected, $payload);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals($update['custom'], $first->get('custom'));
    }

    public function testSetCustomFieldsOnEmptyArray(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $id = Uuid::randomHex();
        $entity = ['id' => $id, 'custom' => []];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'custom' => [
                'foo' => 'bar',
            ],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());
        $expected = ['id' => $id, 'custom' => $update['custom']];
        $payload = $event->getPayloads()[0];
        unset($payload['updatedAt']);

        static::assertEquals($expected, $payload);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals($update['custom'], $first->get('custom'));
    }

    public function testUpdateCustomFieldWithDot(): void
    {
        $this->addCustomFields(['foo.bar' => CustomFieldTypes::TEXT]);

        $id = Uuid::randomHex();
        $entity = ['id' => $id, 'custom' => []];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = [
            'id' => $id,
            'custom' => [
                'foo.bar' => 'foo dot bar',
            ],
        ];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());
        $expected = $update;
        $expected['custom'] = $update['custom'];

        $payload = $event->getPayloads()[0];
        unset($payload['updatedAt']);

        static::assertEquals($expected, $payload);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals($update['custom'], $first->get('custom'));
    }

    public function testSetCustomFieldsToNull(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $id = Uuid::randomHex();
        $entity = ['id' => $id, 'custom' => ['foo' => 'bar']];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = ['id' => $id, 'custom' => null];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());
        $payload = $event->getPayloads()[0];
        unset($payload['updatedAt']);

        static::assertEquals($update, $payload);
        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertNull($first->get('custom'));
    }

    public function testSetCustomFieldsToEmptyArray(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $id = Uuid::randomHex();
        $entity = ['id' => $id, 'custom' => ['foo' => 'bar']];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $update = ['id' => $id, 'custom' => []];
        $result = $repo->update([$update], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getPayloads());
        $payload = $event->getPayloads()[0];
        unset($payload['updatedAt']);

        static::assertEquals(['id' => $id, 'custom' => []], $payload);

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        static::assertNotNull($first);
        static::assertEquals([], $first->get('custom'));
    }

    public function testInheritance(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $repo = $this->getTestRepository();

        $entities = [
            ['id' => $parentId, 'name' => 'parent', 'custom' => ['foo' => 'bar']],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ];
        $context = Context::createDefaultContext();
        $repo->create($entities, $context);

        /** @var ArrayEntity $parent */
        $parent = $repo->search(new Criteria([$parentId]), $context)->first();
        static::assertNotNull($parent);

        static::assertEquals('parent', $parent->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->get('custom'));

        /** @var ArrayEntity $child */
        $child = $repo->search(new Criteria([$childId]), $context)->first();
        static::assertNotNull($child);

        static::assertEquals('child', $child->get('name'));
        static::assertNull($child->get('custom'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom.foo', 'bar'));

        $results = $repo->search($criteria, $context);
        $expected = [$parentId];
        static::assertEquals(array_combine($expected, $expected), $results->getIds());

        /** @var ArrayEntity $parent */
        $parent = $repo->search(new Criteria([$parentId]), $context)->first();
        static::assertNotNull($parent);

        static::assertEquals('parent', $parent->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->get('custom'));

        $criteria = new Criteria([$childId]);

        $context->setConsiderInheritance(true);
        $child = $repo->search($criteria, $context)->first();
        static::assertNotNull($child);

        static::assertEquals('child', $child->get('name'));
        static::assertEquals(['foo' => 'bar'], $child->get('custom'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom.foo', 'bar'));

        $results = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $results->getIds());
    }

    public function testInheritanceCustomFieldsAreMerged(): void
    {
        $this->addCustomFields(['foo' => CustomFieldTypes::TEXT]);

        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $repo = $this->getTestRepository();

        $entities = [
            ['id' => $parentId, 'name' => 'parent', 'custom' => ['foo' => 'bar']],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ];
        $context = Context::createDefaultContext();
        $repo->create($entities, $context);

        /** @var ArrayEntity $parent */
        $parent = $repo->search(new Criteria([$parentId]), $context)->first();
        static::assertNotNull($parent);

        static::assertEquals('parent', $parent->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->get('custom'));
        static::assertEquals('parent', $parent->get('name'));
        static::assertEquals(['foo' => 'bar'], $parent->get('custom'));

        $criteria = new Criteria([$childId]);
        $context->setConsiderInheritance(true);
        $child = $repo->search($criteria, $context)->first();

        static::assertNotNull($child);
        static::assertEquals('child', $child->get('name'));
        static::assertEquals('child', $child->get('name'));
        static::assertEquals(['foo' => 'bar'], $child->get('custom'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom.foo', 'bar'));
        $results = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $results->getIds());

        //#####

        $context->setConsiderInheritance(false);
        $child = $repo->search(new Criteria([$childId]), $context)->first();
        static::assertNotNull($child);

        static::assertEquals('child', $child->get('name'));
        static::assertNull($child->get('custom'));
        static::assertEquals('child', $child->get('name'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom.foo', 'bar'));

        $results = $repo->search($criteria, $context);
        $expected = [$parentId];
        static::assertEquals(array_combine($expected, $expected), $results->getIds());
    }

    public function testCustomFieldAssoc(): void
    {
        $this->addCustomFields(['assoc' => CustomFieldTypes::JSON]);

        $id = Uuid::randomHex();
        $entities = [
            ['id' => $id, 'custom' => ['assoc' => ['foo' => 'bar']]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());
        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertNotEmpty($first);
        static::assertEquals(['assoc' => ['foo' => 'bar']], $first->get('custom'));

        $patch = [
            'id' => $id,
            'custom' => ['assoc' => ['foo' => 'baz']],
        ];

        $repo->update([$patch], Context::createDefaultContext());
        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertNotEmpty($first);
        static::assertEquals(['assoc' => ['foo' => 'baz']], $first->get('custom'));
    }

    public function testCustomFieldArray(): void
    {
        $this->addCustomFields(['array' => CustomFieldTypes::JSON]);

        $id = Uuid::randomHex();
        $entities = [
            ['id' => $id, 'custom' => ['array' => ['foo', 'bar']]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());
        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertNotEmpty($first);
        static::assertEquals(['array' => ['foo', 'bar']], $first->get('custom'));

        $patch = [
            'id' => $id,
            'custom' => ['array' => ['bar', 'baz']],
        ];

        $repo->update([$patch], Context::createDefaultContext());
        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertNotEmpty($first);
        static::assertEquals(['array' => ['bar', 'baz']], $first->get('custom'));
    }

    public function testUpdateDecodedCorrectly(): void
    {
        $this->addCustomFields(['bool' => CustomFieldTypes::BOOL]);

        $a = Uuid::randomHex();
        $b = Uuid::randomHex();

        $entities = [
            ['id' => $a, 'custom' => ['bool' => true]],
            ['id' => $b, 'custom' => ['bool' => false]],
        ];

        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        $update = [
            ['id' => $a, 'custom' => ['bool' => false]],
            ['id' => $b, 'custom' => ['bool' => true]],
        ];
        $events = $repo->update($update, Context::createDefaultContext());
        $event = $events->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        $payloads = $event->getPayloads();
        static::assertCount(2, $payloads);

        static::assertIsBool($payloads[0]['custom']['bool']);
        static::assertIsBool($payloads[1]['custom']['bool']);
    }

    public function testNestedJsonStringValue(): void
    {
        $this->addCustomFields(['json' => CustomFieldTypes::JSON]);
        $date = new \DateTimeImmutable();
        $date = (new \DateTimeImmutable('@' . $date->getTimestamp()))->setTimezone($date->getTimezone());

        $id = Uuid::randomHex();
        $entity = [
            'id' => $id,
            'custom' => ['json' => 'string value'],
            'createdAt' => $date,
        ];

        $repo = $this->getTestRepository();
        $result = $repo->create([$entity], Context::createDefaultContext());
        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertNotNull($event);
        static::assertCount(1, $event->getPayloads());
        static::assertEquals($entity, $event->getPayloads()[0]);
    }

    public function testJsonEncodeDateTime(): void
    {
        $this->addCustomFields(['date' => CustomFieldTypes::DATETIME]);

        $dateTime = new \DateTime('2004-02-29 00:00:00.001');

        $id = Uuid::randomHex();
        $entity = [
            'id' => $id,
            'custom' => ['date' => $dateTime],
            'createdAt' => $dateTime,
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        $encoded = json_decode(json_encode($first), true);
        static::assertEquals($dateTime->format(\DateTime::ATOM), $encoded['custom']['date']);
    }

    public function testJsonEncodeNestedDateTime(): void
    {
        $this->addCustomFields(['json' => CustomFieldTypes::JSON]);

        $dateTime = new \DateTime('2004-02-29 00:00:00.001');

        $id = Uuid::randomHex();
        $entity = [
            'id' => $id,
            'custom' => ['json' => ['date' => $dateTime->format(\DateTime::ATOM)]],
        ];

        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        $first = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
        $encoded = json_decode(json_encode($first), true);
        static::assertEquals($dateTime->format(\DateTime::ATOM), $encoded['custom']['json']['date']);
    }

    private function addCustomFields(array $attributeTypes): void
    {
        $attributeRepo = $this->getContainer()->get('custom_field.repository');

        $attributes = [];
        foreach ($attributeTypes as $name => $type) {
            $attributes[] = ['id' => Uuid::randomHex(), 'name' => $name, 'type' => $type];
        }
        $attributeRepo->create($attributes, Context::createDefaultContext());
    }

    private function getTestRepository(): EntityRepository
    {
        $definition = $this->registerDefinition(
            CustomFieldTestDefinition::class,
            CustomFieldTestTranslationDefinition::class
        );

        return new EntityRepository(
            $definition,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get(EventDispatcherInterface::class)
        );
    }
}
