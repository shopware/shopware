<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\RuleFixture;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

/**
 * @internal
 */
class Cases
{
    private EventDispatcher $dispatcher;

    public function sharedDispatcher(EventDispatcherInterface $dispatcher, EventSubscriberInterface $subscriber): void
    {
        $dispatcher->addListener('event', static function (): void {});
        $dispatcher->removeListener('event', static function (): void {});
        $dispatcher->addSubscriber($subscriber);
        $dispatcher->removeSubscriber($subscriber);
    }

    public function containerDispatcher(TraceableEventDispatcher $dispatcher): void
    {
        $dispatcher->addListener('event', static function (): void {});
    }

    public function ownDispatcher(EventSubscriberInterface $subscriber): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('event', static function (): void {});
        $dispatcher->addSubscriber($subscriber);
    }

    /**
     * @param EventDispatcherInterface&MockObject $dispatcher
     */
    public function doubledDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->addListener('event', static function (): void {});
    }

    public function dispatchOnly(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->dispatch(new \stdClass());
        $dispatcher->getListeners('event');
    }

    public function notADispatcher(\ArrayObject $object): void
    {
        $object->append('addListener');
    }

    public function annotatedContainerDispatcher(ContainerInterface $container): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->addListener('event', static function (): void {});
    }

    public function ownDispatcherProperty(): void
    {
        $this->dispatcher->addListener('event', static function (): void {});
    }

    public function helperWithSharedDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->addEventListener($dispatcher, 'event', static function (): void {});
    }

    public function helperWithOwnDispatcher(): void
    {
        $this->addEventListener(new EventDispatcher(), 'event', static function (): void {});
    }

    private function addEventListener(EventDispatcherInterface $dispatcher, string $eventName, callable $callback): void
    {
    }
}
