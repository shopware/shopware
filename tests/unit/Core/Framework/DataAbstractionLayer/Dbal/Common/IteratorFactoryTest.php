<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
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

    public function testIteratesTranslationsByTheIdOfTheirEntity(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, CategoryTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $connection = static::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $iterator = (new IteratorFactory($connection, $registry))->createIterator($registry->get(CategoryTranslationDefinition::class));

        static::assertSame(
            ['`category_translation`.`category_id`', 'LOWER(HEX(`category_translation`.`category_id`))'],
            $iterator->getQuery()->getSelectParts()
        );
        static::assertStringStartsWith('SELECT DISTINCT', $iterator->getQuery()->getSQL(), 'a translation of several languages is iterated once');
    }
}
