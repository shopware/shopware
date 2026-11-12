<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IteratorFactory::class)]
class IteratorFactoryTest extends TestCase
{
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

    public function testIteratesDefinitionsWithoutIdByTheirPrimaryKeysWithoutVersion(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, CategoryTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $factory = new IteratorFactory(static::createStub(Connection::class), $registry);

        $iterator = $factory->createIterator($registry->get(CategoryTranslationDefinition::class), ['offset' => 100]);

        static::assertInstanceOf(OffsetQuery::class, $iterator);
        static::assertSame(
            ['LOWER(HEX(`category_translation`.`category_id`)) AS `categoryId`', 'LOWER(HEX(`category_translation`.`language_id`)) AS `languageId`'],
            $iterator->getQuery()->getSelectParts(),
            'the keys have the property names of the primary keys of written entities'
        );
        static::assertSame(100, $iterator->getQuery()->getFirstResult());
    }
}
