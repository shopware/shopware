<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Shopware\Core\Content\Flow\FlowEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Test\Integration\Traits\EventHookBehaviour;

/**
 * @internal
 */
#[Package('after-sales')]
class CacheFlowLoaderTest extends TestCase
{
    use EventHookBehaviour;
    use IntegrationTestBehaviour;

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            FlowEvents::FLOW_WRITTEN_EVENT => 'invalidate',
        ], CachedFlowLoader::getSubscribedEvents());
    }

    public function testClearFlowCache(): void
    {
        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');
        $this->onEvent(FlowEvents::FLOW_WRITTEN_EVENT, $listener);

        $flowLoader = static::getContainer()->get(CachedFlowLoader::class);
        $class = new \ReflectionClass($flowLoader);
        $property = $class->getProperty('flows');
        $property->setValue(
            $flowLoader,
            ['abc']
        );

        static::getContainer()->get('flow.repository')->create([[
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
        ]], Context::createDefaultContext());

        static::assertEmpty($property->getValue($flowLoader));
    }
}
