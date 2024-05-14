<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('services-settings')]
class FlowDispatcher implements EventDispatcherInterface
{
    private ContainerInterface $container;

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly FlowFactory $flowFactory,
        private readonly Connection $connection,
    ) {
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
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if (!$event instanceof FlowEventAware) {
            return $event;
        }

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->dispatch($flowLogEvent, $flowLogEvent->getName());

        if (($event instanceof StoppableEventInterface && $event->isPropagationStopped())
            || $event->getContext()->hasState(Context::SKIP_TRIGGER_FLOW)
        ) {
            return $event;
        }

        $storableFlow = $this->flowFactory->create($event);
        $this->callFlowExecutor($storableFlow);

        return $event;
    }

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    /**
     * @return array<array-key, array<array-key, callable(object): void>|callable(object): void>
     */
    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
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

        $flowExecutor = $this->container->get(FlowExecutor::class);

        if ($flowExecutor === null) {
            throw new ServiceNotFoundException(FlowExecutor::class);
        }

        foreach ($flows as $flow) {
            try {
                $payload = $flow['payload'];
                $flowExecutor->execute($payload, $event);
            } catch (ExecuteSequenceException $e) {
                $this->logger->warning(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . 'Sequence id: ' . $e->getSequenceId() . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );

                if ($e->getPrevious() && $this->isInNestedTransaction()) {
                    /**
                     * If we are already in a nested transaction, that does not have save points enabled, we must inform the caller of the rollback.
                     * We do this via an exception, so that the outer transaction can also be rolled back.
                     *
                     * Otherwise, when it attempts to commit, it would fail.
                     */
                    throw $e->getPrevious();
                }
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getFlows(string $eventName): array
    {
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

    private function isInNestedTransaction(): bool
    {
        return $this->connection->getTransactionNestingLevel() !== 1 && !$this->connection->getNestTransactionsWithSavepoints();
    }
}
