<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SystemSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AttributesTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AttributesTestTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AttributesFieldTranslationTest extends TestCase
{
    use KernelTestBehaviour,
        CacheTestBehaviour,
        BasicTestDataBehaviour;

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

        /** @var Entity $result */
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'en_GB', 'system' => 'system'];
        static::assertEquals($expected, $result->getTranslated()['translatedAttributes']);

        $expectedViewData = $expected;
        static::assertEquals($expectedViewData, $result->getTranslated()['translatedAttributes']);

        $chain = [Defaults::LANGUAGE_SYSTEM_DE, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();

        $expected = ['code' => 'de_DE', 'de' => 'de'];
        static::assertEquals($expected, $result->get('translatedAttributes'));

        $expectedViewData = ['code' => 'de_DE', 'de' => 'de', 'system' => 'system'];
        static::assertEquals($expectedViewData, $result->getTranslated()['translatedAttributes']);

        $chain = [$rootLanguageId, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();

        $expected = ['code' => 'root', 'root' => 'root'];
        static::assertEquals($expected, $result->get('translatedAttributes'));
        $expectedViewData = ['code' => 'root', 'root' => 'root', 'system' => 'system'];
        static::assertEquals($expectedViewData, $result->getTranslated()['translatedAttributes']);

        $chain = [$childLanguageId, $rootLanguageId, Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $result = $repo->search(new Criteria([$id]), $context)->first();
        $expected = ['code' => 'child', 'child' => 'child'];
        static::assertEquals($expected, $result->get('translatedAttributes'));
        $expectedViewData = ['code' => 'child', 'child' => 'child', 'root' => 'root', 'system' => 'system'];
        static::assertEquals($expectedViewData, $result->getTranslated()['translatedAttributes']);
    }

    public function testTranslationChainOnFilter(): void
    {
        $this->addAttributes([
            'systemFloat' => AttributeTypes::FLOAT,
            'root' => AttributeTypes::BOOL,
            'int' => AttributeTypes::INT,
            'child' => AttributeTypes::DATETIME,
        ]);

        $id = Uuid::randomHex();

        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->addLanguage($rootId, null);
        $this->addLanguage($childId, $rootId);

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT);
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
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $result = $repo->search($criteria, $context);
        $expected = [$id];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertEquals(1.0, $first->getTranslated()['translatedAttributes']['systemFloat']);
        static::assertArrayNotHasKey('root', $first->getTranslated()['translatedAttributes']);
        static::assertArrayNotHasKey('child', $first->getTranslated()['translatedAttributes']);
        static::assertEquals(1, $first->getTranslated()['translatedAttributes']['int']);

        /** @var array $translated */
        $translated = $first->getTranslated();
        static::assertEquals(1.0, $translated['translatedAttributes']['systemFloat']);
        static::assertArrayNotHasKey('root', $translated['translatedAttributes']);
        static::assertArrayNotHasKey('child', $translated['translatedAttributes']);
        static::assertEquals(1, $translated['translatedAttributes']['int']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$rootId, Defaults::LANGUAGE_SYSTEM]);

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
        static::assertArrayNotHasKey('system', $first->getTranslated()['translatedAttributes']);
        static::assertTrue($first->getTranslated()['translatedAttributes']['root']);
        static::assertArrayNotHasKey('child', $first->getTranslated()['translatedAttributes']);
        static::assertEquals(2, $first->getTranslated()['translatedAttributes']['int']);

        /** @var array $translated */
        $translated = $first->getTranslated();
        static::assertEquals(1.0, $translated['translatedAttributes']['systemFloat']);
        static::assertTrue($translated['translatedAttributes']['root']);
        static::assertArrayNotHasKey('child', $translated['translatedAttributes']);
        static::assertEquals(2, $translated['translatedAttributes']['int']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.child', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // child -> root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$childId, $rootId, Defaults::LANGUAGE_SYSTEM]);

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
        static::assertEquals((new \DateTime($now))->format(\DateTime::ATOM), $first->get('translatedAttributes')['child']);
        static::assertEquals(3, $first->get('translatedAttributes')['int']);

        /** @var array $translated */
        $translated = $first->getTranslated();
        static::assertEquals(1.0, $translated['translatedAttributes']['systemFloat']);
        static::assertTrue($translated['translatedAttributes']['root']);
        static::assertEquals((new \DateTime($now))->format(\DateTime::ATOM), $translated['translatedAttributes']['child']);
        static::assertEquals(3, $translated['translatedAttributes']['int']);
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

        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $rootId = Uuid::randomHex();
        $subId = Uuid::randomHex();

        $this->addLanguage($rootId, null);
        $this->addLanguage($subId, $rootId);

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT);
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
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.parent', 'inherited attribute'));
        $context->setConsiderInheritance(true);
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
        static::assertEquals(1.0, $first->getTranslated()['translatedAttributes']['systemFloat']);
        static::assertArrayNotHasKey('root', $first->getTranslated()['translatedAttributes']);
        static::assertArrayNotHasKey('sub', $first->getTranslated()['translatedAttributes']);
        static::assertEquals(1, $first->getTranslated()['translatedAttributes']['int']);

        /** @var Entity $translated */
        $translated = $first->getTranslated();
        static::assertEquals(1.0, $translated['translatedAttributes']['systemFloat']);
        static::assertArrayNotHasKey('root', $translated['translatedAttributes']);
        static::assertArrayNotHasKey('sub', $translated['translatedAttributes']);
        static::assertEquals(1, $translated['translatedAttributes']['int']);
        static::assertEquals('inherited attribute', $translated['translatedAttributes']['parent']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.sub', $now));
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.parent', 'inherited attribute'));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.root', true));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->getTranslated()['translatedAttributes']);
        static::assertEquals(1, $first->getTranslated()['translatedAttributes']['root']);
        static::assertArrayNotHasKey('sub', $first->getTranslated()['translatedAttributes']);
        static::assertEquals(2, $first->getTranslated()['translatedAttributes']['int']);

        $translated = $first->getTranslated();
        static::assertEquals(1.0, $translated['translatedAttributes']['systemFloat']);
        static::assertEquals(1, $translated['translatedAttributes']['root']);
        static::assertArrayNotHasKey('sub', $translated['translatedAttributes']);
        static::assertEquals(2, $translated['translatedAttributes']['int']);
        static::assertEquals('inherited attribute', $translated['translatedAttributes']['parent']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.sub', $now));
        $context->setConsiderInheritance(false);
        $result = $repo->search($criteria, $context);
        $expected = [];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        // child -> root -> system
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$childId, $rootId, Defaults::LANGUAGE_SYSTEM]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.parent', 'inherited attribute'));
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$parentId, $childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translatedAttributes.systemFloat', 1.0));
        $context->setConsiderInheritance(false);
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
        $context->setConsiderInheritance(true);
        $result = $repo->search($criteria, $context);
        $expected = [$childId];
        static::assertEquals(array_combine($expected, $expected), $result->getIds());

        /** @var Entity $first */
        $first = $result->first();
        static::assertArrayNotHasKey('system', $first->get('translatedAttributes'));
        static::assertArrayNotHasKey('root', $first->get('translatedAttributes'));
        static::assertEquals((new \DateTime($now))->format(\DateTime::ATOM), $first->get('translatedAttributes')['sub']);
        static::assertEquals(3, $first->get('translatedAttributes')['int']);

        $translated = $first->getTranslated();
        static::assertEquals(1.0, $translated['translatedAttributes']['systemFloat']);
        static::assertEquals(1, $translated['translatedAttributes']['root']);
        static::assertEquals((new \DateTime($now))->format(\DateTime::ATOM), $translated['translatedAttributes']['sub']);
        static::assertEquals(3, $translated['translatedAttributes']['int']);
        static::assertEquals('inherited attribute', $translated['translatedAttributes']['parent']);
    }

    protected function addLanguage($id, $rootLanguage): void
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
            $attributes[] = ['id' => Uuid::randomHex(), 'name' => $name, 'type' => $type];
        }
        $attributeRepo->create($attributes, Context::createDefaultContext());
    }
}
