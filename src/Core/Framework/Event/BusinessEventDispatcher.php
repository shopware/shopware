<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\EventAction\EventActionCollection;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BusinessEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var EventActionDefinition
     */
    private $eventActionDefinition;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        DefinitionInstanceRegistry $definitionRegistry,
        EventActionDefinition $eventActionDefinition
    ) {
        $this->dispatcher = $dispatcher;
        $this->eventActionDefinition = $eventActionDefinition;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function dispatch($event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if ($event instanceof BusinessEventInterface) {
            $this->callActions($event);
        }

        return $event;
    }

    public function addListener($eventName, $listener, $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener($eventName, $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function getListeners($eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority($eventName, $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners($eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    private function getActions(string $eventName, Context $context): EventActionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('event_action.eventName', $eventName));

        /** @var EventActionCollection $events */
        $events = $this->definitionRegistry
            ->getRepository($this->eventActionDefinition->getEntityName())
            ->search($criteria, $context)
            ->getEntities();

        return $events;
    }

    private function callActions(BusinessEventInterface $event): void
    {
        $actions = $this->getActions($event->getName(), $event->getContext());

        foreach ($actions as $action) {
            $actionEvent = new BusinessEvent($action->getActionName(), $event, $action->getConfig());
            $this->dispatcher->dispatch($actionEvent, $actionEvent->getActionName());
        }

        $globalEvent = new BusinessEvent(BusinessEvents::GLOBAL_EVENT, $event);
        $this->dispatcher->dispatch($globalEvent, $globalEvent->getActionName());
    }
}
