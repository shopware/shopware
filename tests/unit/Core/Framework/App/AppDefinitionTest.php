<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppDefinition::class)]
class AppDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(AppDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(AppEntity::class, $definition->getEntityClass());
        static::assertSame(AppCollection::class, $definition->getCollectionClass());
        static::assertSame('6.3.1.0', $definition->since());
        static::assertNotSame([], $definition->getDefaults());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): AppDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [AppDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(AppDefinition::ENTITY_NAME);
        static::assertInstanceOf(AppDefinition::class, $definition);

        return $definition;
    }
}
