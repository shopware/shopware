<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NestedEventCollection::class)]
class NestedEventCollectionTest extends TestCase
{
    public function testGetApiAlias(): void
    {
        static::assertSame('dal_nested_event_collection', (new NestedEventCollection())->getApiAlias());
    }

    public function testGetFlatEventListThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Method "Shopware\Core\Framework\Event\NestedEventCollection::getFlatEventList()" is deprecated and will be removed in v6.8.0.0.'
        ));

        (new NestedEventCollection())->getFlatEventList();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetFlatEventListFlattensTheContainedEvents(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [PluginDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $event = new EntityLoadedEvent(
            $registry->getByEntityName(PluginDefinition::ENTITY_NAME),
            [new ArrayEntity(['id' => 'entity-id'])],
            Context::createDefaultContext(),
        );

        $flat = (new NestedEventCollection([$event]))->getFlatEventList();

        static::assertSame([$event], array_values($flat->getElements()));
    }
}
