<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Shopware\Core\Content\Flow\FlowEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('business-ops')]
class CacheFlowLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            FlowEvents::FLOW_WRITTEN_EVENT => 'invalidate',
        ], CachedFlowLoader::getSubscribedEvents());
    }

    public function testClearFlowCache(): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener(FlowEvents::FLOW_WRITTEN_EVENT, $listener);

        $flowLoader = $this->getContainer()->get(CachedFlowLoader::class);
        $class = new \ReflectionClass($flowLoader);
        $property = $class->getProperty('flows');
        $property->setAccessible(true);
        $property->setValue(
            $flowLoader,
            ['abc']
        );

        $this->getContainer()->get('flow.repository')->create([[
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
        ]], Context::createDefaultContext());

        static::assertEmpty($property->getValue($flowLoader));
    }
}
