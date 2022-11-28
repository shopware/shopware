<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Api;

use Shopware\Core\Framework\MessageQueue\Subscriber\CountHandledMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class ConsumeMessagesController extends AbstractController
{
    private ServiceLocator $receiverLocator;

    private MessageBusInterface $bus;

    private StopWorkerOnRestartSignalListener $stopWorkerOnRestartSignalListener;

    private StopWorkerOnSigtermSignalListener $stopWorkerOnSigtermSignalListener;

    private DispatchPcntlSignalListener $dispatchPcntlSignalListener;

    private EarlyReturnMessagesListener $earlyReturnListener;

    private string $defaultTransportName;

    private string $memoryLimit;

    private MessageQueueStatsSubscriber $statsSubscriber;

    /**
     * @internal
     */
    public function __construct(
        ServiceLocator $receiverLocator,
        MessageBusInterface $bus,
        StopWorkerOnRestartSignalListener $stopWorkerOnRestartSignalListener,
        StopWorkerOnSigtermSignalListener $stopWorkerOnSigtermSignalListener,
        DispatchPcntlSignalListener $dispatchPcntlSignalListener,
        EarlyReturnMessagesListener $earlyReturnListener,
        MessageQueueStatsSubscriber $statsSubscriber,
        string $defaultTransportName,
        string $memoryLimit
    ) {
        $this->receiverLocator = $receiverLocator;
        $this->bus = $bus;
        $this->stopWorkerOnRestartSignalListener = $stopWorkerOnRestartSignalListener;
        $this->stopWorkerOnSigtermSignalListener = $stopWorkerOnSigtermSignalListener;
        $this->dispatchPcntlSignalListener = $dispatchPcntlSignalListener;
        $this->earlyReturnListener = $earlyReturnListener;
        $this->defaultTransportName = $defaultTransportName;
        $this->memoryLimit = $memoryLimit;
        $this->statsSubscriber = $statsSubscriber;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/message-queue/consume", name="api.action.message-queue.consume", methods={"POST"})
     */
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
        $workerDispatcher->addSubscriber($this->stopWorkerOnSigtermSignalListener);
        $workerDispatcher->addSubscriber($this->dispatchPcntlSignalListener);
        $workerDispatcher->addSubscriber($this->earlyReturnListener);

        if ($this->memoryLimit !== '-1') {
            $workerDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener(
                MemorySizeCalculator::convertToBytes($this->memoryLimit)
            ));
        }

        $worker = new Worker([$this->defaultTransportName => $receiver], $this->bus, $workerDispatcher);

        $worker->run(['sleep' => 50]);

        return new JsonResponse(['handledMessages' => $listener->getHandledMessages()]);
    }
}
