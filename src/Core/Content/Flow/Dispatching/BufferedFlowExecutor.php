<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-import-type FlowHolder from AbstractFlowLoader
 * @phpstan-import-type EventGroupedFlowHolders from AbstractFlowLoader
 *
 * @experimental stableVersion:v6.8.0 feature:FLOW_EXECUTION_AFTER_BUSINESS_PROCESS
 */
#[Package('after-sales')]
class BufferedFlowExecutor
{
    private const MAXIMUM_EXECUTION_DEPTH = 10;

    public function __construct(
        private readonly BufferedFlowQueue $bufferedFlowQueue,
        private readonly AbstractFlowLoader $flowLoader,
        private readonly FlowFactory $flowFactory,
        private readonly FlowExecutor $flowExecutor,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function executeBufferedFlows(): void
    {
        $flowExecutionDepth = 0;
        // If after the first iteration the buffer is still not empty, this means that the triggered flows added new
        // events to the buffer, so we execute them as well.
        while (!$this->bufferedFlowQueue->isEmpty() && $flowExecutionDepth < self::MAXIMUM_EXECUTION_DEPTH) {
            $bufferedFlows = $this->bufferedFlowQueue->dequeueFlows();
            $eventGroupedFlowHolders = $this->flowLoader->load();

            foreach ($bufferedFlows as $bufferedFlow) {
                $storableFlow = $this->flowFactory->create($bufferedFlow);
                $flowHolders = $this->getFlowHoldersForEvent($storableFlow->getName(), $eventGroupedFlowHolders);

                if (empty($flowHolders)) {
                    continue;
                }

                $this->flowExecutor->executeFlows($flowHolders, $storableFlow);
            }

            ++$flowExecutionDepth;
        }

        if ($flowExecutionDepth >= self::MAXIMUM_EXECUTION_DEPTH) {
            $eventNames = array_map(
                static fn (FlowEventAware $event) => $event->getName(),
                $this->bufferedFlowQueue->dequeueFlows(),
            );

            $this->logger->error(
                'Maximum execution depth reached for buffered flow executor. This might be caused by a cyclic flow execution.',
                ['bufferedEvents' => $eventNames],
            );
        }
    }

    /**
     * @param EventGroupedFlowHolders $eventGroupedFlowHolders
     *
     * @return array<FlowHolder>
     */
    private function getFlowHoldersForEvent(string $eventName, array $eventGroupedFlowHolders): array
    {
        $flowHolders = [];
        if (\array_key_exists($eventName, $eventGroupedFlowHolders)) {
            $flowHolders = $eventGroupedFlowHolders[$eventName];
        }

        return $flowHolders;
    }
}
