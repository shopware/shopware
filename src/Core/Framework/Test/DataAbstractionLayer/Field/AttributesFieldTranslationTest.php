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
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AttributesTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AttributesTestTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AttributesFieldTranslationTest extends TestCase
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

    public function testTranslatedAttributes(): void
    {
        $this->addAttributes([
            'code' => AttributeTypes::TEXT,
            'de' => AttributeTypes::TEXT,
            'system' => AttributeTypes::TEXT,
            'systemFloat' => AttributeTypes::FLOAT,
            'root' => AttributeTypes::TEXT,
            'child' => AttributeTypes::TEXT,
        ]);

        $rootLanguageId = Uuid::uuid4()->getHex();
        $childLanguageId = Uuid::uuid4()->getHex();
        $this->addLanguage($rootLanguageId, null);
        $this->addLanguage($childLanguageId, $rootLanguageId);

        $repo = $this->getTestRepository();
        $context = Context::createDefaultContext();

        $id = Uuid::uuid4()->getHex();

        $entity = [
            'id' => $id,
            'name' => 'translated',
            'translations' => [
                'en_GB' => [
                    'translatedAttributes' => [
                        'code' => 'en_GB',
                        'system' => 'system',
                    ],
                ],
                'de_DE' => [
                    'translatedAttributes' => [
                        'code' => 'de_DE',
                        'de' => 'de',
                    ],
                ],
                $rootLanguageId => [
                    'translatedAttributes' => [
                        'code' => 'root',
                        'root' => 'root',
                    ],
                ],
                $childLanguageId => [
                    'translatedAttributes' => [
                        'code' => 'child',
                        'child' => 'child',
                    ],
                ],
            ],
        ];
        $result = $repo->create([$entity], $context);

        $event = $result->getEventByDefinition(AttributesTestDefinition::class);
        static::assertCount(1, $event->getIds());

        $event = $result->getEventByDefinition(AttributesTestTranslationDefinition::class);
        static::assertCount(4, $event->getIds());

        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'en_GB', 'system' => 'system'];
        static::assertEquals($expected, $result->get('translatedAttributes'));
        $expectedViewData = $expected;
        static::assertEquals($expectedViewData, $result->getViewData()->get('translatedAttributes'));

        $chain = [Defaults::LANGUAGE_SYSTEM_DE, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'de_DE', 'de' => 'de'];
        static::assertEquals($expected, $result->get('translatedAttributes'));
        $expectedViewData = ['code' => 'de_DE', 'de' => 'de', 'system' => 'system'];
        static::assertEquals($expectedViewData, $result->getViewData()->get('translatedAttributes'));

        $chain = [$rootLanguageId, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'root', 'root' => 'root'];
        static::assertEquals($expected, $result->get('translatedAttributes'));
        $expectedViewData = ['code' => 'root', 'root' => 'root', 'system' => 'system'];
        static::assertEquals($expectedViewData, $result->getViewData()->get('translatedAttributes'));

        $chain = [$childLanguageId, $rootLanguageId, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'child', 'child' => 'child'];
        static::assertEquals($expected, $result->get('translatedAttributes'));
        $expectedViewData = ['code' => 'child', 'child' => 'child', 'root' => 'root', 'system' => 'system'];
        static::assertEquals($expectedViewData, $result->getViewData()->get('translatedAttributes'));
    }

    public function testTranslationChainOnFilter(): void
    {
        $this->addAttributes([
            'systemFloat' => AttributeTypes::FLOAT,
            'root' => AttributeTypes::BOOL,
            'int' => AttributeTypes::INT,
            'child' => AttributeTypes::DATETIME,
        ]);

        $id = Uuid::uuid4()->getHex();

        $rootId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();

        $this->addLanguage($rootId, null);
        $this->addLanguage($childId, $rootId);

        $now = (new \DateTime())->format(Defaults::DATE_FORMAT);
        $entity = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'translatedAttributes' => [
                        'systemFloat' => 1.0,
                        'int' => 1,
                    ],
                ],
                $rootId => [
                    'translatedAttributes' => [
                        'root' => true,
                        'int' => 2,
                    ],
                ],
                $childId => [
                    'translatedAttributes' => [
                        'child' => $now,
                        'int' => 3,
                    ],
                ],
            ],
        ];
        $repo = $this->getTestRepository();
        $repo->create([$entity], Context::createDefaultContext());

        // system
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertEquals(1.0, $first->get('translatedAttributes')['systemFloat']);
        static::assertArrayNotHasKey('root', $first->get('translatedAttributes'));
        static::assertArrayNotHasKey('child', $first->get('translatedAttributes'));
        static::assertEquals(1, $first->get('translatedAttributes')['int']);

        /** @var Entity $viewData */
        $viewData = $first->getViewData();
        static::assertEquals(1.0, $viewData->get('translatedAttributes')['systemFloat']);
        static::assertArrayNotHasKey('root', $viewData->get('translatedAttributes'));
        static::assertArrayNotHasKey('child', $viewData->get('translatedAttributes'));
        static::assertEquals(1, $viewData->get('translatedAttributes')['int']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // root -> system
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, [$rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->get('translatedAttributes'));
        static::assertEquals(1, $first->get('translatedAttributes')['root']);
        static::assertArrayNotHasKey('child', $first->get('translatedAttributes'));
        static::assertEquals(2, $first->get('translatedAttributes')['int']);

        /** @var Entity $viewData */
        $viewData = $first->getViewData();
        static::assertEquals(1.0, $viewData->get('translatedAttributes')['systemFloat']);
        static::assertEquals(1, $viewData->get('translatedAttributes')['root']);
        static::assertArrayNotHasKey('child', $viewData->get('translatedAttributes'));
        static::assertEquals(2, $viewData->get('translatedAttributes')['int']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // child -> root -> system
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, [$childId, $rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->get('translatedAttributes'));
        static::assertArrayNotHasKey('root', $first->get('translatedAttributes'));
        static::assertEquals((new \DateTime($now))->format(Defaults::DATE_FORMAT), $first->get('translatedAttributes')['child']);
        static::assertEquals(3, $first->get('translatedAttributes')['int']);

        /** @var Entity $viewData */
        $viewData = $first->getViewData();
        static::assertEquals(1.0, $viewData->get('translatedAttributes')['systemFloat']);
        static::assertEquals(1, $viewData->get('translatedAttributes')['root']);
        static::assertEquals((new \DateTime($now))->format(Defaults::DATE_FORMAT), $viewData->get('translatedAttributes')['child']);
        static::assertEquals(3, $viewData->get('translatedAttributes')['int']);
    }

    public function testTranslatedAttributesWithInheritance(): void
    {
        $this->addAttributes([
            'systemFloat' => AttributeTypes::FLOAT,
            'root' => AttributeTypes::BOOL,
            'sub' => AttributeTypes::DATETIME,
            'int' => AttributeTypes::INT,
            'parent' => AttributeTypes::TEXT,
        ]);

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();

        $rootId = Uuid::uuid4()->getHex();
        $subId = Uuid::uuid4()->getHex();

        $this->addLanguage($rootId, null);
        $this->addLanguage($subId, $rootId);

        $now = (new \DateTime())->format(Defaults::DATE_FORMAT);
        $entities = [
            [
                'id' => $parentId,
                'translatedAttributes' => [
                    'parent' => 'inherited attribute',
                ],
            ],
            [
                'id' => $childId,
                'parentId' => $parentId,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'translatedAttributes' => [
                            'systemFloat' => 1.0,
                            'int' => 1,
                        ],
                    ],
                    $rootId => [
                        'translatedAttributes' => [
                            'root' => true,
                            'int' => 2,
                        ],
                    ],
                    $childId => [
                        'translatedAttributes' => [
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
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.parent', 'inherited attribute'));
        $result = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertEquals(1.0, $first->get('translatedAttributes')['systemFloat']);
        static::assertArrayNotHasKey('root', $first->get('translatedAttributes'));
        static::assertArrayNotHasKey('sub', $first->get('translatedAttributes'));
        static::assertEquals(1, $first->get('translatedAttributes')['int']);

        /** @var Entity $viewData */
        $viewData = $first->getViewData();
        static::assertEquals(1.0, $viewData->get('translatedAttributes')['systemFloat']);
        static::assertArrayNotHasKey('root', $viewData->get('translatedAttributes'));
        static::assertArrayNotHasKey('sub', $viewData->get('translatedAttributes'));
        static::assertEquals(1, $viewData->get('translatedAttributes')['int']);
        static::assertEquals('inherited attribute', $viewData->get('translatedAttributes')['parent']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.sub', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // root -> system
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, [$rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.parent', 'inherited attribute'));
        $result = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->get('translatedAttributes'));
        static::assertEquals(1, $first->get('translatedAttributes')['root']);
        static::assertArrayNotHasKey('sub', $first->get('translatedAttributes'));
        static::assertEquals(2, $first->get('translatedAttributes')['int']);

        /** @var Entity $viewData */
        $viewData = $first->getViewData();
        static::assertEquals(1.0, $viewData->get('translatedAttributes')['systemFloat']);
        static::assertEquals(1, $viewData->get('translatedAttributes')['root']);
        static::assertArrayNotHasKey('sub', $viewData->get('translatedAttributes'));
        static::assertEquals(2, $viewData->get('translatedAttributes')['int']);
        static::assertEquals('inherited attribute', $viewData->get('translatedAttributes')['parent']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.sub', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // child -> root -> system
        $context = new Context(new SourceContext(), [], Defaults::CURRENCY, [$childId, $rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.parent', 'inherited attribute'));
        $result = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.sub', $now));
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->get('translatedAttributes'));
        static::assertArrayNotHasKey('root', $first->get('translatedAttributes'));
        static::assertEquals((new \DateTime($now))->format(Defaults::DATE_FORMAT), $first->get('translatedAttributes')['sub']);
        static::assertEquals(3, $first->get('translatedAttributes')['int']);

        /** @var Entity $viewData */
        $viewData = $first->getViewData();
        static::assertEquals(1.0, $viewData->get('translatedAttributes')['systemFloat']);
        static::assertEquals(1, $viewData->get('translatedAttributes')['root']);
        static::assertEquals((new \DateTime($now))->format(Defaults::DATE_FORMAT), $viewData->get('translatedAttributes')['sub']);
        static::assertEquals(3, $viewData->get('translatedAttributes')['int']);
        static::assertEquals('inherited attribute', $viewData->get('translatedAttributes')['parent']);
    }

    protected function addLanguage($id, $rootLanguage): void
    {
        $translationCodeId = Uuid::uuid4()->getHex();
        $languageRepository = $this->getContainer()->get('language.repository');
        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'parentId' => $rootLanguage,
                    'name' => $id,
                    'localeId' => Defaults::LOCALE_SYSTEM,
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
        return new EntityRepository(
            AttributesTestDefinition::class,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get(EventDispatcherInterface::class)
        );
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
}
