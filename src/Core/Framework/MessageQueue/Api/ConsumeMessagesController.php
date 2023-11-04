<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Subscriber\CountHandledMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class ConsumeMessagesController extends AbstractController
{
    /**
     * @param ServiceLocator<ReceiverInterface> $receiverLocator
     *
     * @internal
     */
    public function __construct(
        private readonly ServiceLocator $receiverLocator,
        private readonly MessageBusInterface $bus,
        private readonly StopWorkerOnRestartSignalListener $stopWorkerOnRestartSignalListener,
        private readonly EarlyReturnMessagesListener $earlyReturnListener,
        private readonly MessageQueueStatsSubscriber $statsSubscriber,
        private readonly string $defaultTransportName,
        private readonly string $memoryLimit
    ) {
    }

    #[Route(path: '/api/_action/message-queue/consume', name: 'api.action.message-queue.consume', methods: ['POST'])]
    public function consumeMessages(Request $request): JsonResponse
    {
        $receiverName = $request->get('receiver');

        if (!$receiverName || !$this->receiverLocator->has($receiverName)) {
            throw new \RuntimeException('No receiver name provided.');
        }

        $receiver = $this->receiverLocator->get($receiverName);

        $workerDispatcher = new EventDispatcher();
        $listener = new CountHandledMessagesListener();
        $workerDispatcher->addSubscriber($listener);
        $workerDispatcher->addSubscriber($this->statsSubscriber);
        $workerDispatcher->addSubscriber($this->stopWorkerOnRestartSignalListener);
        $workerDispatcher->addSubscriber($this->earlyReturnListener);

        if ($this->memoryLimit !== '-1') {
            $workerDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener(
                MemorySizeCalculator::convertToBytes($this->memoryLimit)
            ));
        }

        $worker = new Worker([$this->defaultTransportName => $receiver], $this->bus, $workerDispatcher);

        $worker->run();

        return new JsonResponse(['handledMessages' => $listener->getHandledMessages()]);
    }
}
