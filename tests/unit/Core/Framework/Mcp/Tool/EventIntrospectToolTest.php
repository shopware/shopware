<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\EventIntrospectTool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EventIntrospectTool::class)]
class EventIntrospectToolTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;

    private EventIntrospectTool $tool;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->tool = new EventIntrospectTool($this->dispatcher);
    }

    public function testReturnTypeIsAlwaysString(): void
    {
        $result = ($this->tool)('NonExistentEvent');
        static::assertIsString($result);

        $decoded = json_decode($result, true);
        static::assertIsArray($decoded);
        static::assertArrayHasKey('success', $decoded);
    }

    public function testUnknownEventReturnsErrorNotException(): void
    {
        $result = json_decode(($this->tool)('NonExistentEvent99999Xyz'), true);

        static::assertFalse($result['success']);
        static::assertStringContainsString('NonExistentEvent99999Xyz', $result['error']);
    }

    public function testErrorMessageContainsUsageHint(): void
    {
        $result = json_decode(($this->tool)('SomeMissingEvent'), true);

        static::assertFalse($result['success']);
        static::assertStringContainsString('partial', $result['error']);
    }

    public function testExactFqcnResolvesWhenClassExists(): void
    {
        // Register a listener under a real class name so the dispatcher knows it
        $this->dispatcher->addListener(\stdClass::class, static function (): void {});

        $result = json_decode(($this->tool)(\stdClass::class), true);

        static::assertTrue($result['success']);
        static::assertEquals(\stdClass::class, $result['data']['event_class']);
    }

    public function testPartialNameResolvesViaDispatcherListeners(): void
    {
        // Register a listener using a class name that contains "stdClass"
        $this->dispatcher->addListener(\stdClass::class, static function (): void {});

        $result = json_decode(($this->tool)('stdClass'), true);

        static::assertTrue($result['success']);
        static::assertStringContainsString('stdClass', $result['data']['event_class']);
    }

    public function testActiveListenersReturnedSortedByPriorityDescending(): void
    {
        $eventName = \stdClass::class;

        $lowPriorityListener = static function (): void {};
        $highPriorityListener = static function (): void {};

        $this->dispatcher->addListener($eventName, $lowPriorityListener, 10);
        $this->dispatcher->addListener($eventName, $highPriorityListener, 100);

        $result = json_decode(($this->tool)($eventName), true);
        $listeners = $result['data']['active_listeners_in_this_instance'];

        static::assertCount(2, $listeners);
        static::assertGreaterThanOrEqual($listeners[1]['priority'], $listeners[0]['priority']);
    }

    public function testSubscriberSkeletonContainsRequiredElements(): void
    {
        $this->dispatcher->addListener(\stdClass::class, static function (): void {});

        $result = json_decode(($this->tool)(\stdClass::class), true);
        $skeleton = $result['data']['subscriber_skeleton'];

        static::assertStringContainsString('EventSubscriberInterface', $skeleton);
        static::assertStringContainsString('getSubscribedEvents', $skeleton);
        static::assertStringContainsString('stdClass', $skeleton);
    }

    public function testSubscriberSkeletonPriorityHigherThanExistingListeners(): void
    {
        $eventName = \stdClass::class;
        $this->dispatcher->addListener($eventName, static function (): void {}, 50);

        $result = json_decode(($this->tool)($eventName), true);
        $skeleton = $result['data']['subscriber_skeleton'];

        // Suggested priority should be 50 + 10 = 60
        static::assertStringContainsString('60', $skeleton);
    }

    public function testSubscriberSkeletonWithNoListenersUsesDefaultPriority(): void
    {
        $this->dispatcher->addListener(\stdClass::class, static function (): void {}, 0);

        $result = json_decode(($this->tool)(\stdClass::class), true);
        $skeleton = $result['data']['subscriber_skeleton'];

        // No listeners means maxPriority = 0, suggested = 10
        static::assertStringContainsString('10', $skeleton);
    }

    public function testGettersAreExtractedFromReflection(): void
    {
        $this->dispatcher->addListener(\stdClass::class, static function (): void {});

        $result = json_decode(($this->tool)(\stdClass::class), true);
        $getters = $result['data']['available_getters'];

        // stdClass has no getters — the list should be empty but present
        static::assertIsArray($getters);
    }

    public function testConstructorParamsAreExtractedFromReflection(): void
    {
        $this->dispatcher->addListener(\stdClass::class, static function (): void {});

        $result = json_decode(($this->tool)(\stdClass::class), true);

        static::assertIsArray($result['data']['constructor_params']);
    }
}
