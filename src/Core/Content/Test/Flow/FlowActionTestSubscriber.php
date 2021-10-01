<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;

class FlowActionTestSubscriber extends FlowAction
{
    public $events = [];

    public $actions = [];

    public $lastActionConfig;

    public static function getSubscribedEvents()
    {
        return [
            TestBusinessEvent::class => 'testEvent',
            BusinessEvents::GLOBAL_EVENT => 'globalEvent',
            'unit_test_action_true' => 'handle',
            'unit_test_action_false' => 'handleFalse',
            'unit_test_action_next' => 'handleNext',
        ];
    }

    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public static function getName(): string
    {
        return 'unit_test_action';
    }

    public function handle(FlowEvent $event): void
    {
        $this->incrAction($event->getActionName());
        $this->lastActionConfig = $event->getConfig();
    }

    public function handleFalse(FlowEvent $event): void
    {
        $this->incrAction($event->getActionName());
        $this->lastActionConfig = $event->getConfig();
    }

    public function handleNext(FlowEvent $event): void
    {
        $this->incrAction($event->getActionName());
        $this->lastActionConfig = $event->getConfig();
    }

    public function globalEvent(): void
    {
        $this->incrEvent(BusinessEvents::GLOBAL_EVENT);
    }

    public function testEvent(TestBusinessEvent $event): void
    {
        $this->incrEvent($event->getName());
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
