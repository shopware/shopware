<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
 */
class ActionTestSubscriber implements EventSubscriberInterface
{
    public $events = [];

    public $actions = [];

    public $lastActionConfig;

    public static function getSubscribedEvents()
    {
        return [
            TestBusinessEvent::class => 'testEvent',
            BusinessEvents::GLOBAL_EVENT => 'globalEvent',
            'unit_test_action' => 'actionUnit',
            '2nd_unit_test_action' => 'actionUnit',
        ];
    }

    public function globalEvent(): void
    {
        $this->incrEvent(BusinessEvents::GLOBAL_EVENT);
    }

    public function testEvent(TestBusinessEvent $event): void
    {
        $this->incrEvent($event->getName());
    }

    public function actionUnit(BusinessEvent $event): void
    {
        $this->incrAction($event->getActionName());
        $this->lastActionConfig = $event->getConfig();
    }

    private function incrEvent(string $eventName): void
    {
        if (!isset($this->events[$eventName])) {
            $this->events[$eventName] = 0;
        }

        ++$this->events[$eventName];
    }

    private function incrAction(string $actionName): void
    {
        if (!isset($this->actions[$actionName])) {
            $this->actions[$actionName] = 0;
        }

        ++$this->actions[$actionName];
    }
}
