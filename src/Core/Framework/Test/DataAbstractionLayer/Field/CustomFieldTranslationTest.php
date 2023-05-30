<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class CustomFieldTranslationTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use BasicTestDataBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->executeStatement('DROP TABLE IF EXISTS `attribute_test`');
        $this->connection->executeStatement('
            CREATE TABLE `attribute_test` (
              id BINARY(16) NOT NULL PRIMARY KEY,
              parent_id BINARY(16) NULL,
              name varchar(255) DEFAULT NULL,
              custom json DEFAULT NULL,
              created_at DATETIME(3) NOT NULL,
              updated_at DATETIME(3) NULL
        )');

        $this->connection->executeStatement('DROP TABLE IF EXISTS `attribute_test_translation`');
        $this->connection->executeStatement('
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

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `attribute_test_translation`');
        $this->connection->executeStatement('DROP TABLE `attribute_test`');
    }

    public function testRawIsNotInherited(): void
    {
        $this->addCustomFields(['root' => CustomFieldTypes::TEXT]);
        $id = 'c724803ea1cc4e72abc264a1020000bf';
        $entity = [
            'id' => $id,
            'name' => 'test',
            'translations' => [
                'en-GB' => [
                    'customTranslated' => [
                        'root' => 'test',
                    ],
                ],
                'de-DE' => [
                    'customTranslated' => null,
                ],
            ],
        ];

        $chain = [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM];
        $repo = $this->getTestRepository();

        $context = Context::createDefaultContext();
        $repo->create([$entity], $context);

        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);

        /** @var Entity $result */
        $result = $repo->search(new Criteria([$id]), $context)->first();

        static::assertNull($result->get('customTranslated'));
        static::assertNotNull($result->getTranslation('customTranslated'));
    }

    public function testTranslatedCustomFields(): void
    {
        $this->addCustomFields([
            'code' => CustomFieldTypes::TEXT,
            'de' => CustomFieldTypes::TEXT,
            'system' => CustomFieldTypes::TEXT,
            'systemFloat' => CustomFieldTypes::FLOAT,
            'root' => CustomFieldTypes::TEXT,
            'child' => CustomFieldTypes::TEXT,
        ]);

        $rootLanguageId = Uuid::randomHex();
        $childLanguageId = Uuid::randomHex();
        $this->addLanguage($rootLanguageId, null);
        $this->addLanguage($childLanguageId, $rootLanguageId);

        $repo = $this->getTestRepository();
        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();

        $entity = [
            'id' => $id,
            'name' => 'translated',
            'translations' => [
                'en-GB' => [
                    'customTranslated' => [
                        'code' => 'en-GB',
                        'system' => 'system',
                    ],
                ],
                'de-DE' => [
                    'customTranslated' => [
                        'code' => 'de-DE',
                        'de' => 'de',
                    ],
                ],
                $rootLanguageId => [
                    'customTranslated' => [
                        'code' => 'root',
                        'root' => 'root',
                    ],
                ],
                $childLanguageId => [
                    'customTranslated' => [
                        'code' => 'child',
                        'child' => 'child',
                    ],
                ],
            ],
        ];
        $result = $repo->create([$entity], $context);

        $event = $result->getEventByEntityName(CustomFieldTestDefinition::ENTITY_NAME);
        static::assertCount(1, $event->getIds());

        $event = $result->getEventByEntityName(CustomFieldTestTranslationDefinition::ENTITY_NAME);
        static::assertCount(4, $event->getIds());

        /** @var Entity $result */
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'en-GB', 'system' => 'system'];
        static::assertEquals($expected, $result->getTranslated()['customTranslated']);

        $expectedViewData = $expected;
        static::assertEquals($expectedViewData, $result->getTranslated()['customTranslated']);

        $chain = [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();

        $expected = ['de' => 'de', 'code' => 'de-DE'];
        static::assertEquals($expected, $result->get('customTranslated'));

        $expectedViewData = ['code' => 'de-DE', 'system' => 'system', 'de' => 'de'];
        static::assertEquals($expectedViewData, $result->getTranslated()['customTranslated']);

        $chain = [$rootLanguageId, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();

        $expected = ['code' => 'root', 'root' => 'root'];
        static::assertEquals($expected, $result->get('customTranslated'));
        $expectedViewData = ['code' => 'root', 'system' => 'system', 'root' => 'root'];
        static::assertEquals($expectedViewData, $result->getTranslated()['customTranslated']);

        $chain = [$childLanguageId, $rootLanguageId, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'child', 'child' => 'child'];
        static::assertEquals($expected, $result->get('customTranslated'));
        $expectedViewData = ['code' => 'child', 'system' => 'system', 'root' => 'root', 'child' => 'child'];
        static::assertEquals($expectedViewData, $result->getTranslated()['customTranslated']);
    }

    public function testTranslationChainOnFilter(): void
    {
        $this->addCustomFields([
            'systemFloat' => CustomFieldTypes::FLOAT,
            'root' => CustomFieldTypes::BOOL,
            'int' => CustomFieldTypes::INT,
            'child' => CustomFieldTypes::DATETIME,
        ]);

        $id = Uuid::randomHex();

        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->addLanguage($rootId, null);
        $this->addLanguage($childId, $rootId);

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $entity = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'customTranslated' => [
                        'systemFloat' => 1.0,
                        'int' => 1,
                    ],
                ],
                $rootId => [
                    'customTranslated' => [
                        'root' => true,
                        'int' => 2,
                    ],
                ],
                $childId => [
                    'customTranslated' => [
                        'child' => $now,
                        'int' => 3,
                    ],
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        // system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertSame(1.0, $first->getTranslated()['customTranslated']['systemFloat']);
        static::assertArrayNotHasKey('root', $first->getTranslated()['customTranslated']);
        static::assertArrayNotHasKey('child', $first->getTranslated()['customTranslated']);
        static::assertSame(1, $first->getTranslated()['customTranslated']['int']);

        $translated = $first->getTranslated();
        static::assertSame(1.0, $translated['customTranslated']['systemFloat']);
        static::assertArrayNotHasKey('root', $translated['customTranslated']);
        static::assertArrayNotHasKey('child', $translated['customTranslated']);
        static::assertSame(1, $translated['customTranslated']['int']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.root', true));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->getTranslated()['customTranslated']);
        static::assertTrue($first->getTranslated()['customTranslated']['root']);
        static::assertArrayNotHasKey('child', $first->getTranslated()['customTranslated']);
        static::assertSame(2, $first->getTranslated()['customTranslated']['int']);

        $translated = $first->getTranslated();
        static::assertSame(1.0, $translated['customTranslated']['systemFloat']);
        static::assertTrue($translated['customTranslated']['root']);
        static::assertArrayNotHasKey('child', $translated['customTranslated']);
        static::assertSame(2, $translated['customTranslated']['int']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // child -> root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$childId, $rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.child', $now));

        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->get('customTranslated'));
        static::assertArrayNotHasKey('root', $first->get('customTranslated'));
        static::assertSame((new \DateTime($now))->format(\DateTime::ATOM), $first->get('customTranslated')['child']);
        static::assertSame(3, $first->get('customTranslated')['int']);

        $translated = $first->getTranslated();
        static::assertSame(1.0, $translated['customTranslated']['systemFloat']);
        static::assertTrue($translated['customTranslated']['root']);
        static::assertSame((new \DateTime($now))->format(\DateTime::ATOM), $translated['customTranslated']['child']);
        static::assertSame(3, $translated['customTranslated']['int']);
    }

    public function testTranslatedCustomFieldsWithInheritance(): void
    {
        $this->addCustomFields([
            'systemFloat' => CustomFieldTypes::FLOAT,
            'root' => CustomFieldTypes::BOOL,
            'sub' => CustomFieldTypes::DATETIME,
            'int' => CustomFieldTypes::INT,
            'parent' => CustomFieldTypes::TEXT,
        ]);

        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $rootId = Uuid::randomHex();
        $subId = Uuid::randomHex();

        $this->addLanguage($rootId, null);
        $this->addLanguage($subId, $rootId);

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $entities = [
            [
                'id' => $parentId,
                'customTranslated' => [
                    'parent' => 'inherited attribute',
                ],
            ],
            [
                'id' => $childId,
                'parentId' => $parentId,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'customTranslated' => [
                            'systemFloat' => 1.0,
                            'int' => 1,
                        ],
                    ],
                    $rootId => [
                        'customTranslated' => [
                            'root' => true,
                            'int' => 2,
                        ],
                    ],
                    $childId => [
                        'customTranslated' => [
                            'sub' => $now,
                            'int' => 3,
                        ],
                    ],
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create($entities, Context::createDefaultContext());

        // system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.parent', 'inherited attribute'));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId, $parentId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertSame(1.0, $first->getTranslated()['customTranslated']['systemFloat']);
        static::assertArrayNotHasKey('root', $first->getTranslated()['customTranslated']);
        static::assertArrayNotHasKey('sub', $first->getTranslated()['customTranslated']);
        static::assertSame(1, $first->getTranslated()['customTranslated']['int']);

        $translated = $first->getTranslated();
        static::assertSame(1.0, $translated['customTranslated']['systemFloat']);
        static::assertArrayNotHasKey('root', $translated['customTranslated']);
        static::assertArrayNotHasKey('sub', $translated['customTranslated']);
        static::assertSame(1, $translated['customTranslated']['int']);
        static::assertSame('inherited attribute', $translated['customTranslated']['parent']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.root', true));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.sub', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.parent', 'inherited attribute'));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId, $parentId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.systemFloat', 1.0));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.root', true));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->getTranslated()['customTranslated']);
        static::assertTrue($first->getTranslated()['customTranslated']['root']);
        static::assertArrayNotHasKey('sub', $first->getTranslated()['customTranslated']);
        static::assertSame(2, $first->getTranslated()['customTranslated']['int']);

        $translated = $first->getTranslated();
        static::assertSame(1.0, $translated['customTranslated']['systemFloat']);
        static::assertTrue($translated['customTranslated']['root']);
        static::assertArrayNotHasKey('sub', $translated['customTranslated']);
        static::assertSame(2, $translated['customTranslated']['int']);
        static::assertSame('inherited attribute', $translated['customTranslated']['parent']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.sub', $now));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // child -> root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$childId, $rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.parent', 'inherited attribute'));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId, $parentId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.systemFloat', 1.0));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customTranslated.sub', $now));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertSame((new \DateTime($now))->format(\DateTime::ATOM), $first->get('customTranslated')['sub']);
        static::assertSame(3, $first->get('customTranslated')['int']);

        $translated = $first->getTranslated();
        static::assertSame(1.0, $translated['customTranslated']['systemFloat']);
        static::assertTrue($translated['customTranslated']['root']);
        static::assertSame((new \DateTime($now))->format(\DateTime::ATOM), $translated['customTranslated']['sub']);
        static::assertSame(3, $translated['customTranslated']['int']);
        static::assertSame('inherited attribute', $translated['customTranslated']['parent']);
    }

    protected function addLanguage(string $id, ?string $rootLanguage): void
    {
        $translationCodeId = Uuid::randomHex();
        $languageRepository = $this->getContainer()->get('language.repository');
        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'parentId' => $rootLanguage,
                    'name' => $id,
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'translationCode' => [
                        'id' => $translationCodeId,
                        'name' => 'x-' . $translationCodeId,
                        'code' => 'x-' . $translationCodeId,
                        'territory' => $translationCodeId,
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }

    protected function getTestRepository(): EntityRepository
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
            $this->getContainer()->get(EventDispatcherInterface::class),
            $this->getContainer()->get(EntityLoadedEventFactory::class)
        );
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
}
