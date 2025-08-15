<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Message\FlowMessage;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-import-type FlowHolder from AbstractFlowLoader
 * @phpstan-import-type EventGroupedFlowHolders from AbstractFlowLoader
 *
 * @experimental stableVersion:v6.8.0 feature:FLOW_EXECUTION_AFTER_BUSINESS_PROCESS
 */
#[AsMessageHandler(handles: FlowMessage::class)]
#[Package('after-sales')]
class BufferedFlowExecutor
{
    private const MAXIMUM_EXECUTION_DEPTH = 10;

    public function __construct(
        private readonly AbstractFlowLoader $flowLoader,
        private readonly FlowFactory $flowFactory,
        private readonly FlowExecutor $flowExecutor,
        private readonly LoggerInterface $logger,
        private readonly FlowExecutionDepthProvider $flowExecutionDepthProvider,
    ) {
    }

    public function __invoke(FlowMessage $message): void
    {
        $this->flowExecutionDepthProvider->setFlowExecutionDepth($message->getDepth());
        if ($this->flowExecutionDepthProvider->getFlowExecutionDepth() < self::MAXIMUM_EXECUTION_DEPTH) {
            $bufferedFlow = $message->getEvent();
            $eventGroupedFlowHolders = $this->flowLoader->load();
            $storableFlow = $this->flowFactory->create($bufferedFlow);
            $flowHolders = $this->getFlowHoldersForEvent($storableFlow->getName(), $eventGroupedFlowHolders);

            if (empty($flowHolders)) {
                return;
            }

            $this->flowExecutor->executeFlows($flowHolders, $storableFlow);
        } else {
            $this->logger->error(
                'Maximum execution depth reached for buffered flow executor. This might be caused by a cyclic flow execution.',
                ['bufferedEvent' => $message->getEvent()->getName()],
            );
        }
    }

    public function getFlowExecutionDepth(): int
    {
        return $this->flowExecutionDepth;
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
