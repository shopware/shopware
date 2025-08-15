<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @internal not intended for decoration or replacement
 *
 * @experimental stableVersion:v6.8.0 feature:FLOW_EXECUTION_AFTER_BUSINESS_PROCESS
 */
#[Package('after-sales')]
class BufferedFlowExecutionTriggersListener implements EventSubscriberInterface, ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ReceiverInterface $flowMessageReceiver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        if (!Feature::isActive('FLOW_EXECUTION_AFTER_BUSINESS_PROCESS')) {
            return [];
        }

        // Run buffered flows after any execution environment finishes one unit of work
        return [
            KernelEvents::TERMINATE => 'triggerBufferedFlowExecution',
            WorkerMessageHandledEvent::class => 'triggerBufferedFlowExecution',
            ConsoleEvents::TERMINATE => 'triggerBufferedFlowExecution',
        ];
    }

    public function triggerBufferedFlowExecution(): void
    {
        xdebug_break();
        $bufferedFlows = $this->flowMessageReceiver->get();
        if (empty($bufferedFlows)) {
            return;
        }

        $flowExecutor = $this->container->get(BufferedFlowExecutor::class);
        do {
            foreach ($bufferedFlows as $bufferedFlow) {
                $flowExecutor->__invoke($bufferedFlow->getMessage());
            }
        } while ($bufferedFlows = $this->flowMessageReceiver->get());
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        return [BufferedFlowExecutor::class];
    }
}
