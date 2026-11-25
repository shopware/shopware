<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IteratorFactory::class)]
class IteratorFactoryTest extends TestCase
{
    private DefinitionInstanceRegistry&Stub $registry;

    private IteratorFactory $factory;

    protected function setUp(): void
    {
        $this->registry = static::createStub(DefinitionInstanceRegistry::class);
        $this->factory = new IteratorFactory(static::createStub(Connection::class), $this->registry);
    }

    public function testSingleIdUsesUuidPagination(): void
    {
        $definition = new MediaDefinition();
        $definition->compile($this->registry);
        $this->registry->method('getByEntityName')->willReturn($definition);

        $iterator = $this->factory->createIterator('media', limit: 100);

        static::assertInstanceOf(LastIdQuery::class, $iterator);
        static::assertArrayNotHasKey('lastId', $iterator->getQuery()->getParameters());
        static::assertSame(100, $iterator->getQuery()->getMaxResults());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInitialIterationKeepsOffsetPaginationBefore68(): void
    {
        $definition = new MediaDefinition();
        $definition->compile($this->registry);

        static::assertInstanceOf(OffsetQuery::class, $this->factory->createIterator($definition));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUuidCursorResumesAfterDisablingTheFeature(): void
    {
        $definition = new MediaDefinition();
        $definition->compile($this->registry);
        $id = '31323334353637383930313233343536';

        $iterator = $this->factory->createIterator($definition, ['offset' => $id]);

        static::assertInstanceOf(LastIdQuery::class, $iterator);
        static::assertSame(Uuid::fromHexToBytes($id), $iterator->getQuery()->getParameter('lastId'));
        static::assertSame(ParameterType::BINARY, $iterator->getQuery()->getParameterType('lastId'));
        static::assertSame(0, $iterator->getQuery()->getFirstResult());
    }

    public function testNumericCursorKeepsOffsetPagination(): void
    {
        $definition = new MediaDefinition();
        $definition->compile($this->registry);

        $iterator = $this->factory->createIterator($definition, ['offset' => 42]);

        static::assertInstanceOf(OffsetQuery::class, $iterator);
        static::assertSame(42, $iterator->getQuery()->getFirstResult());
    }

    public function testCompositePrimaryKeyKeepsOffsetPagination(): void
    {
        $definition = new ProductManufacturerDefinition();
        $definition->compile($this->registry);

        static::assertInstanceOf(OffsetQuery::class, $this->factory->createIterator($definition));
    }

    public function testAutoIncrementKeepsNumericCursor(): void
    {
        $definition = new CategoryDefinition();
        $definition->compile($this->registry);

        $iterator = $this->factory->createIterator($definition, ['offset' => 42]);

        static::assertInstanceOf(LastIdQuery::class, $iterator);
        static::assertSame(42, $iterator->getQuery()->getParameter('lastId'));
    }

    public function testPrimaryKeyWithAnotherColumnNameKeepsOffsetPagination(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'custom_entity';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('custom_id', 'customId'))->addFlags(new PrimaryKey()),
                ]);
            }
        };
        $definition->compile($registry);
        $factory = new IteratorFactory(static::createStub(Connection::class), $registry);

        static::assertInstanceOf(OffsetQuery::class, $factory->createIterator($definition));
    }

    public function testCreateIteratorAddsVersionFilterWhenVersionAwareAndProvided(): void
    {
        $connection = static::createStub(Connection::class);
        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $definition = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'order';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }

            public function isVersionAware(): bool
            {
                return true;
            }
        };

        $definition->compile($registry);

        $factory = new IteratorFactory($connection, $registry);

        $iterator = $factory->createIterator($definition, null, 50, Defaults::LIVE_VERSION);

        $params = $iterator->getQuery()->getParameters();
        static::assertArrayHasKey('versionId', $params);
        static::assertSame(50, $iterator->getQuery()->getMaxResults());
    }

    public function testIteratesTranslationsByTheIdOfTheirEntity(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, CategoryTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $connection = static::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $iterator = (new IteratorFactory($connection, $registry))->createIterator($registry->get(CategoryTranslationDefinition::class), null, 50, Defaults::LIVE_VERSION);
        $sql = $iterator->getQuery()->getSQL();

        static::assertSame(
            ['`category_translation`.`category_id`', 'LOWER(HEX(`category_translation`.`category_id`))'],
            $iterator->getQuery()->getSelectParts()
        );
        static::assertStringStartsWith('SELECT DISTINCT', $sql, 'a translation of several languages is iterated once');
        static::assertStringContainsString('WHERE `category_translation`.`category_version_id` = :versionId', $sql, 'the version of the entity the translation belongs to is filtered');
        static::assertStringContainsString('ORDER BY `category_translation`.`category_id`', $sql, 'the offset pagination needs a stable order');
    }

    public function testFailsForTranslationsOfEntitiesWithoutTranslations(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $parent = new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'broken';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([(new IdField('id', 'id'))->addFlags(new PrimaryKey())]);
            }
        };

        $translation = new class extends EntityTranslationDefinition {
            public EntityDefinition $parent;

            public function getEntityName(): string
            {
                return 'broken_translation';
            }

            public function getParentDefinition(): EntityDefinition
            {
                return $this->parent;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([]);
            }
        };
        $translation->parent = $parent;
        $parent->compile($registry);
        $translation->compile($registry);

        $this->expectExceptionObject(DataAbstractionLayerException::missingParentForeignKey('broken'));

        (new IteratorFactory(static::createStub(Connection::class), $registry))->createIterator($translation);
    }
}
