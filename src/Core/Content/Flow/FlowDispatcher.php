<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\FlowEvent;
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

    private ContainerInterface $container;

    private AbstractFlowLoader $flowLoader;

    private LoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        AbstractFlowLoader $flowLoader,
        LoggerInterface $logger
    ) {
        $this->dispatcher = $dispatcher;
        $this->flowLoader = $flowLoader;
        $this->logger = $logger;
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

        if (!$event instanceof BusinessEventInterface) {
            return $event;
        }

        if ($event instanceof BusinessEvent || $event instanceof FlowEvent) {
            return $event;
        }

        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        $this->callFlowExecutor($event);

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

    private function callFlowExecutor(BusinessEventInterface $event): void
    {
        $flows = $this->flowLoader->load($event->getName());

        if ($flows->count() === 0) {
            return;
        }

        /** @var FlowExecutor|null $flowExecutor */
        $flowExecutor = $this->container->get(FlowExecutor::class);

        if ($flowExecutor === null) {
            throw new ServiceNotFoundException(FlowExecutor::class);
        }

        foreach ($flows as $flow) {
            try {
                $flowExecutor->execute($flow, $event);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n"
                );
            }
        }
    }
}
