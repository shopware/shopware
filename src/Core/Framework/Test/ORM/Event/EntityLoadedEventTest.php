<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;

class EntityLoadedEventTest extends TestCase
{
    public function testExtractManyToOne(): void
    {
        $a = new ArrayStruct(['id' => 'many_to_one_A']);

        $root = new ArrayStruct(['id' => 'A', 'many_to_one' => $a]);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$root]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a]),
                    $context
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractManyToOneRecursive(): void
    {
        $a = new ArrayStruct(['id' => 'many_to_one_A']);
        $b = new ArrayStruct(['id' => 'many_to_one_B', 'many_to_one' => $a]);
        $c = new ArrayStruct(['id' => 'many_to_one_C', 'many_to_one' => $b]);

        $root = new ArrayStruct(['id' => 'A', 'many_to_one' => $c]);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$root]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a, $b, $c]),
                    $context
                ),
            ]),
            $event->getEvents()
        );

        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$c]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a, $b]),
                    $context
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractOneToMany(): void
    {
        $a = new ArrayStruct(['id' => 'one_to_many_A']);
        $b = new ArrayStruct(['id' => 'one_to_many_B']);

        $entity = new ArrayStruct(['id' => 'A', 'one_to_many' => new EntityCollection([$a, $b])]);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$entity]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a, $b]),
                    $context
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractOneToManyRecursive(): void
    {
        $a = new ArrayStruct(['id' => 'one_to_many_A']);
        $b = new ArrayStruct(['id' => 'one_to_many_B']);

        $c = new ArrayStruct(['id' => 'one_to_many_C', 'one_to_many' => new EntityCollection([$a, $b])]);

        $entity = new ArrayStruct(['id' => 'A', 'one_to_many' => new EntityCollection([$c])]);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$entity]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a, $b, $c]),
                    $context
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractManyToMany(): void
    {
        $a = new ArrayStruct(['id' => 'many_to_many_A']);
        $b = new ArrayStruct(['id' => 'many_to_many_B']);

        $entity = new ArrayStruct(['id' => 'A', 'many_to_many' => new EntityCollection([$a, $b])]);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$entity]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a, $b]),
                    $context
                ),
            ]),
            $event->getEvents()
        );
    }

    public function testExtractManyToManyRecursive(): void
    {
        $a = new ArrayStruct(['id' => 'many_to_many_A']);
        $b = new ArrayStruct(['id' => 'many_to_many_B']);

        $c = new ArrayStruct(['id' => 'many_to_many_C', 'many_to_many' => new EntityCollection([$a, $b])]);

        $entity = new ArrayStruct(['id' => 'A', 'many_to_many' => new EntityCollection([$c])]);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $event = new EntityLoadedEvent(TestDefinition::class, new EntityCollection([$entity]), $context);

        static::assertEquals(
            new NestedEventCollection([
                new EntityLoadedEvent(
                    TestDefinition::class,
                    new EntityCollection([$a, $b, $c]),
                    $context
                ),
            ]),
            $event->getEvents()
        );
    }
}

class TestDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'test';
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new ManyToOneAssociationField('many_to_one', 'many_to_one', self::class, true),
            new OneToManyAssociationField('one_to_many', self::class, 'test_id', true),
            new ManyToManyAssociationField('many_to_many', self::class, ProductCategoryDefinition::class, true, 'test_id', 'test_id'),
        ]);
    }
}
