<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

class EntityLoadedEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $definition = new TestDefinition();
        $definition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $this->getContainer()->set(TestDefinition::class, $definition);
    }

    public function testExtractManyToOne(): void
    {
        $a = new ArrayEntity(['id' => 'many_to_one_A']);

        $root = new ArrayEntity(['id' => 'A', 'many_to_one' => $a]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$root], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$a],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractManyToOneRecursive(): void
    {
        $a = new ArrayEntity(['id' => 'many_to_one_A']);
        $b = new ArrayEntity(['id' => 'many_to_one_B', 'many_to_one' => $a]);
        $c = new ArrayEntity(['id' => 'many_to_one_C', 'many_to_one' => $b]);

        $root = new ArrayEntity(['id' => 'A', 'many_to_one' => $c]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$root], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$c, $b, $a],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );

        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$c], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$b, $a],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractOneToMany(): void
    {
        $a = new ArrayEntity(['id' => 'one_to_many_A']);
        $b = new ArrayEntity(['id' => 'one_to_many_B']);

        $entity = new ArrayEntity(['id' => 'A', 'one_to_many' => [$a, $b]]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$entity], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$a, $b],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractOneToManyRecursive(): void
    {
        $a = new ArrayEntity(['id' => 'one_to_many_A']);
        $b = new ArrayEntity(['id' => 'one_to_many_B']);

        $c = new ArrayEntity(['id' => 'one_to_many_C', 'one_to_many' => [$a, $b]]);

        $entity = new ArrayEntity(['id' => 'A', 'one_to_many' => [$c]]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$entity], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$c, $a, $b],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractManyToMany(): void
    {
        $a = new ArrayEntity(['id' => 'many_to_many_A']);
        $b = new ArrayEntity(['id' => 'many_to_many_B']);

        $entity = new ArrayEntity(['id' => 'A', 'many_to_many' => [$a, $b]]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$entity], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$a, $b],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractManyToManyRecursive(): void
    {
        $a = new ArrayEntity(['id' => 'many_to_many_A']);
        $b = new ArrayEntity(['id' => 'many_to_many_B']);

        $c = new ArrayEntity(['id' => 'many_to_many_C', 'many_to_many' => [$a, $b]]);

        $entity = new ArrayEntity(['id' => 'A', 'many_to_many' => [$c]]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$entity], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$c, $a, $b],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractNestedRelationsRecursively(): void
    {
        $aNested = new ArrayEntity(['id' => 'many_to_one_B']);
        $a = new ArrayEntity(['id' => 'many_to_one_A', 'one_to_many' => [$aNested]]);

        $root = new ArrayEntity(['id' => 'A', 'many_to_one' => $a]);

        $context = Context::createDefaultContext();
        $event = new EntityLoadedEvent($this->getContainer()->get(TestDefinition::class), [$root], $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    $this->getContainer()->get(TestDefinition::class),
                    [$a, $aNested],
                    $context,
                    false
                ),
            ]),
            $event->getEvents()
        );

        /** @var EntityLoadedEvent $subEvent */
        $subEvent = $event->getEvents()->first();

        // check if sub events are marked as nested so they don't create nested events again
        $property = ReflectionHelper::getProperty(\get_class($subEvent), 'nested');
        static::assertFalse($property->getValue($subEvent));

        // there should be no more events as they are dispatched within the $root event
        static::assertNull($subEvent->getEvents());
    }
}

class TestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'test';

    public function getEntityName(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new ManyToOneAssociationField('many_to_one', 'many_to_one', self::class, 'id', true),
            new OneToManyAssociationField('one_to_many', self::class, 'test_id'),
            new ManyToManyAssociationField('many_to_many', self::class, ProductCategoryDefinition::class, 'test_id', 'test_id'),
        ]);
    }
}
