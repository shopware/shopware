<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityLoadedEvent::class)]
class EntityLoadedEventTest extends TestCase
{
    public function testTheEventNameIsDerivedFromTheEntityName(): void
    {
        static::assertSame('plugin.loaded', $this->createEvent()->getName());
    }

    public function testGettersReturnTheConstructorArguments(): void
    {
        $event = $this->createEvent();

        static::assertSame(PluginDefinition::ENTITY_NAME, $event->getDefinition()->getEntityName());
        static::assertCount(2, $event->getEntities());
        static::assertNull($event->getEvents());
        static::assertSame(['entity-a', 'entity-b'], $event->getIds());
        static::assertCount(2, iterator_to_array($event->getIterator()));
    }

    /**
     * @return EntityLoadedEvent<ArrayEntity>
     */
    private function createEvent(): EntityLoadedEvent
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [PluginDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        return new EntityLoadedEvent(
            $registry->getByEntityName(PluginDefinition::ENTITY_NAME),
            [new ArrayEntity(['id' => 'entity-a']), new ArrayEntity(['id' => 'entity-b'])],
            Context::createDefaultContext(),
        );
    }
}
