<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityDeleteEvent::class)]
class EntityDeleteEventTest extends TestCase
{
    public function testGetters(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $command = new DeleteCommand(
            $registry->getByEntityName('product'),
            ['id' => $ids->getBytes('p1')],
            new EntityExistence('product', ['id' => $ids->get('p1')], true, true, true, [])
        );

        $event = EntityDeleteEvent::create($writeContext, [
            $command,
        ]);

        static::assertSame($writeContext, $event->getWriteContext());
        static::assertSame($context, $event->getContext());
        static::assertSame([$command], $event->getCommands());
    }

    public function testFilled(): void
    {
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $event = EntityDeleteEvent::create($writeContext, []);

        static::assertFalse($event->filled());

        $ids = new IdsCollection();

        $registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $command = new DeleteCommand(
            $registry->getByEntityName('product'),
            ['id' => $ids->getBytes('p1')],
            new EntityExistence('product', ['id' => $ids->get('p1')], true, true, true, [])
        );

        $event = EntityDeleteEvent::create($writeContext, [
            $command,
        ]);

        static::assertTrue($event->filled());
    }

    public function testGetIds(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition(), new MediaDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $productDelete = new DeleteCommand(
            $registry->get(ProductDefinition::class),
            ['id' => $ids->getBytes('p1')],
            new EntityExistence('product', ['id' => $ids->getBytes('p1')], true, true, true, [])
        );

        $mediaDelete = new DeleteCommand(
            $registry->get(MediaDefinition::class),
            ['id' => $ids->getBytes('m1')],
            new EntityExistence('media', ['id' => $ids->getBytes('m1')], true, true, true, [])
        );

        $event = EntityDeleteEvent::create($writeContext, [
            $productDelete,
            $mediaDelete,
        ]);

        static::assertSame([$ids->get('p1')], $event->getIds('product'));
        static::assertSame([$ids->get('m1')], $event->getIds('media'));
    }

    public function testCallbacksAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $event = EntityDeleteEvent::create($writeContext, []);

        $callbackFactory = fn () => new class {
            public int $counter = 0;

            public function __invoke(): void
            {
                ++$this->counter;
            }
        };

        $callback1 = $callbackFactory();
        $callback2 = $callbackFactory();

        $event->addSuccess(\Closure::fromCallable($callback1));
        $event->addSuccess(\Closure::fromCallable($callback1));
        $event->addError(\Closure::fromCallable($callback2));

        $event->success();

        static::assertEquals(2, $callback1->counter);

        $event->error();
        static::assertEquals(1, $callback2->counter);
    }
}
