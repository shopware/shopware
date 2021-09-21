<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Event\EventAction\EventActionCollection;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use FlowDispatcher instead.
 */
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
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', '6.5.0', 'Will be removed in v6.5.0, use FlowDispatcher instead.');

        $event = $this->dispatcher->dispatch($event, $eventName);

        if (Feature::isActive('FEATURE_NEXT_17858')) {
            return $event;
        }

        if (!$event instanceof BusinessEventInterface || $event instanceof FlowEvent) {
            return $event;
        }

        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        $this->callActions($event);

        return $event;
    }

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    /**
     * @param callable $listener can not use native type hint as it is incompatible with symfony <5.3.4
     */
    public function removeListener(string $eventName, $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * @param callable $listener can not use native type hint as it is incompatible with symfony <5.3.4
     */
    public function getListenerPriority(string $eventName, $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    private function getActions(BusinessEventInterface $event, Context $context): EventActionCollection
    {
        $name = $event->getName();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('event_action.eventName', $name));
        $criteria->addFilter(new EqualsFilter('event_action.active', true));

        $criteria->addFilter(new OrFilter([
            new EqualsFilter('event_action.rules.id', null),
            new EqualsAnyFilter('event_action.rules.id', $context->getRuleIds()),
        ]));

        if ($event instanceof SalesChannelAware) {
            $criteria->addFilter(new OrFilter([
                new EqualsFilter('salesChannels.id', $event->getSalesChannelId()),
                new EqualsFilter('salesChannels.id', null),
            ]));
        }

        /** @var EventActionCollection $events */
        $events = $this->definitionRegistry
            ->getRepository($this->eventActionDefinition->getEntityName())
            ->search($criteria, $context)
            ->getEntities();

        return $events;
    }

    private function callActions(BusinessEventInterface $event): void
    {
        $actions = $this->getActions($event, $event->getContext());

        foreach ($actions as $action) {
            $actionEvent = new BusinessEvent($action->getActionName(), $event, $action->getConfig());
            $this->dispatcher->dispatch($actionEvent, $actionEvent->getActionName());
        }

        $globalEvent = new BusinessEvent(BusinessEvents::GLOBAL_EVENT, $event);
        $this->dispatcher->dispatch($globalEvent, $globalEvent->getActionName());
    }
}
