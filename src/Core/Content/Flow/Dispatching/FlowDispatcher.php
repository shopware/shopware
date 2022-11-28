<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package business-ops
 *
 * @internal not intended for decoration or replacement
 */
class FlowDispatcher implements EventDispatcherInterface
{
    private EventDispatcherInterface $dispatcher;

    private ContainerInterface $container;

    private LoggerInterface $logger;

    private FlowFactory $flowFactory;

    public function __construct(EventDispatcherInterface $dispatcher, LoggerInterface $logger, FlowFactory $flowFactory)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->flowFactory = $flowFactory;
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @template TEvent of object
     *
     * @param TEvent $event
     *
     * @return TEvent
     */
    public function dispatch($event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if (!$event instanceof FlowEventAware) {
            return $event;
        }

        if (Feature::isActive('v6.5.0.0')) {
            $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
            $this->dispatcher->dispatch($flowLogEvent, $flowLogEvent->getName());
        }

        if (
            $event instanceof FlowEvent
            || ($event instanceof StoppableEventInterface && $event->isPropagationStopped())
            || $event->getContext()->hasState(Context::SKIP_TRIGGER_FLOW)
        ) {
            return $event;
        }

        $storableFlow = $this->flowFactory->create($event);

        /** @deprecated tag:v6.5.0 Will be removed */
        if (!Feature::isActive('v6.5.0.0')) {
            $storableFlow->setOriginalEvent($event);
        }

        $this->callFlowExecutor($storableFlow);

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
     * @param string $eventName
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

    private function callFlowExecutor(StorableFlow $event): void
    {
        $flows = $this->getFlows($event->getName());

        if (empty($flows)) {
            return;
        }

        /** @var FlowExecutor|null $flowExecutor */
        $flowExecutor = $this->container->get(FlowExecutor::class);

        if ($flowExecutor === null) {
            throw new ServiceNotFoundException(FlowExecutor::class);
        }

        foreach ($flows as $flow) {
            try {
                /** @var Flow $payload */
                $payload = $flow['payload'];
                $flowExecutor->execute($payload, $event);
            } catch (ExecuteSequenceException $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . 'Sequence id: ' . $e->getSequenceId() . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n"
                );
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n"
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getFlows(string $eventName): array
    {
        /** @var AbstractFlowLoader|null $flowLoader */
        $flowLoader = $this->container->get(FlowLoader::class);

        if ($flowLoader === null) {
            throw new ServiceNotFoundException(FlowExecutor::class);
        }

        $flows = $flowLoader->load();

        $result = [];
        if (\array_key_exists($eventName, $flows)) {
            $result = $flows[$eventName];
        }

        return $result;
    }
}
