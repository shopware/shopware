<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Hookable\HookableBusinessEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Hookable\WriteResultMerger;

/**
 * @internal
 */
#[CoversClass(HookableEventFactory::class)]
class HookableEventFactoryTest extends TestCase
{
    private HookableEventFactory $hookableEventFactory;

    private BusinessEventEncoder $businessEventEncoder;

    private WriteResultMerger $writeResultMerger;

    private HookableEventCollector $hookableEventCollector;

    protected function setUp(): void
    {
        $this->businessEventEncoder = $this->createMock(BusinessEventEncoder::class);
        $this->writeResultMerger = $this->createMock(WriteResultMerger::class);
        $this->hookableEventCollector = $this->createMock(HookableEventCollector::class);

        $this->hookableEventFactory = new HookableEventFactory(
            $this->businessEventEncoder,
            $this->writeResultMerger,
            $this->hookableEventCollector
        );
    }

    public function testCreateHookablesForStorableFlow(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $result = $this->hookableEventFactory->createHookablesFor($flow);

        static::assertSame([], $result);
    }

    public function testCreateHookablesForHookableEvent(): void
    {
        $hookableEvent = $this->createMock(Hookable::class);

        $result = $this->hookableEventFactory->createHookablesFor($hookableEvent);

        static::assertSame([$hookableEvent], $result);
    }

    public function testCreateHookablesForFlowEventAware(): void
    {
        $flowEventAware = $this->createMock(FlowEventAware::class);

        $result = $this->hookableEventFactory->createHookablesFor($flowEventAware);

        static::assertCount(1, $result);
        static::assertInstanceOf(HookableBusinessEvent::class, $result[0]);
    }

    public function testCreateHookablesForEntityWrittenContainerEvent(): void
    {
        $entityWrittenEvent = $this->createEntityWrittenEvent();
        $mergedWrittenEvent = $this->createEntityWrittenEvent();

        $hookableEventCollector = static::createStub(HookableEventCollector::class);
        $hookableEventCollector->method('getHookableEntities')->willReturn(['product', 'customer']);

        $writeResultMerger = static::createStub(WriteResultMerger::class);
        $writeResultMerger->method('mergeWriteResults')->willReturn($mergedWrittenEvent);

        $hookableEventFactory = new HookableEventFactory(
            $this->businessEventEncoder,
            $writeResultMerger,
            $hookableEventCollector
        );

        $entityWrittenContainerEvent = static::createStub(EntityWrittenContainerEvent::class);
        $entityWrittenContainerEvent
            ->method('getEventByEntityName')
            ->willReturnCallback(function (string $entityName) use ($entityWrittenEvent) {
                return match ($entityName) {
                    'product' => $entityWrittenEvent,
                    'product_translation' => null,
                    'customer' => null,
                    'customer_translation' => null,
                    default => null,
                };
            });

        $result = $hookableEventFactory->createHookablesFor($entityWrittenContainerEvent);

        static::assertCount(1, $result);
        static::assertInstanceOf(HookableEntityWrittenEvent::class, $result[0]);
    }

    public function testCreateHookablesForNonHookableEvent(): void
    {
        $nonHookableEvent = new \stdClass();

        $result = $this->hookableEventFactory->createHookablesFor($nonHookableEvent);

        static::assertSame([], $result);
    }

    private function createEntityWrittenEvent(): EntityWrittenEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenEvent(
            ProductDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    Uuid::randomHex(),
                    [],
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT,
                    null,
                    null
                ),
            ],
            $context
        );
    }
}
