<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

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
}
