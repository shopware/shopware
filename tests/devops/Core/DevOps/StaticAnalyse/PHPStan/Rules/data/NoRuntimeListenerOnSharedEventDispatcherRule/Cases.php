<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\RuleFixture;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

/**
 * @internal
 */
class Cases
{
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
}
