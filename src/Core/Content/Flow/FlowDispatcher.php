<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowDispatcher implements EventDispatcherInterface
{
    private EventDispatcherInterface $dispatcher;

    private ?FlowCollection $flows = null;

    private ContainerInterface $container;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @param object $event
     */
    public function dispatch($event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if ($event instanceof BusinessEventInterface) {
            $this->callFlowExecutor($event);
        }

        return $event;
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     * @param int      $priority
     */
    public function addListener($eventName, $listener, $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    /**
     * @param string   $eventName
     * @param callable $listener
     */
    public function removeListener($eventName, $listener): void
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
     * @param string   $eventName
     * @param callable $listener
     */
    public function getListenerPriority($eventName, $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    public function clearInternalFlowCache(): void
    {
        $this->flows = null;
    }

    private function callFlowExecutor(BusinessEventInterface $event): void
    {
        $flowsForEvent = $this->getFlows($event->getName());

        if ($flowsForEvent->count() === 0) {
            return;
        }

        foreach ($flowsForEvent as $flow) {
            if (!$this->container->has(FlowExecutor::class)) {
                throw new ServiceNotFoundException(FlowExecutor::class);
            }

            // TODO: if statement will be removed after removing flag FEATURE_NEXT_8225
            /** @var FlowExecutor $flowExecutor */
            $flowExecutor = $this->container->get(FlowExecutor::class);
            if ($flowExecutor !== null) {
                $flowExecutor->execute($flow, $event);
            }
        }
    }

    private function getFlows(string $eventName): FlowCollection
    {
        if ($this->flows) {
            return $this->flows;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('eventName', $eventName));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));
        $criteria->getAssociation('flowSequences')->addSorting(
            new FieldSorting('parentId'),
            new FieldSorting('trueCase'),
            new FieldSorting('position')
        );

        if (!$this->container->has('flow.repository')) {
            throw new ServiceNotFoundException('flow.repository');
        }

        // TODO: if statement will be removed after removing flag FEATURE_NEXT_8225
        /** @var EntityRepositoryInterface $flowRepository */
        $flowRepository = $this->container->get('flow.repository');
        $flows = new FlowCollection();
        if ($flowRepository !== null) {
            /** @var FlowCollection $flows */
            $flows = $flowRepository->search($criteria, Context::createDefaultContext())->getEntities();
        }

        return $this->flows = $flows;
    }
}
