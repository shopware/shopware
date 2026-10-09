<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\FlowEvent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFlowEventDefinition::class)]
class AppFlowEventDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(AppFlowEventDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(AppFlowEventEntity::class, $definition->getEntityClass());
        static::assertSame(AppFlowEventCollection::class, $definition->getCollectionClass());
        static::assertSame('6.5.2.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): AppFlowEventDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [AppFlowEventDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(AppFlowEventDefinition::ENTITY_NAME);
        static::assertInstanceOf(AppFlowEventDefinition::class, $definition);

        return $definition;
    }
}
