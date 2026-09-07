<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginDefinition::class)]
class PluginDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(PluginDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(PluginEntity::class, $definition->getEntityClass());
        static::assertSame(PluginCollection::class, $definition->getCollectionClass());
        static::assertSame('6.0.0.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): PluginDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [PluginDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(PluginDefinition::ENTITY_NAME);
        static::assertInstanceOf(PluginDefinition::class, $definition);

        return $definition;
    }
}
