<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Api;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\MessageQueue\Subscriber\CountHandledMessagesListener;
use Shopware\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnSigtermSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class ConsumeMessagesController extends AbstractController
{
    /**
     * @var ServiceLocator
     */
    private $receiverLocator;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var int
     */
    private $pollInterval;

    /**
     * @var StopWorkerOnRestartSignalListener
     */
    private $stopWorkerOnRestartSignalListener;

    /**
     * @var StopWorkerOnSigtermSignalListener
     */
    private $stopWorkerOnSigtermSignalListener;

    /**
     * @var DispatchPcntlSignalListener
     */
    private $dispatchPcntlSignalListener;

    /**
     * @var EarlyReturnMessagesListener
     */
    private $earlyReturnListener;

    private string $defaultTransportName;

    public function __construct(
        ServiceLocator $receiverLocator,
        MessageBusInterface $bus,
        int $pollInterval,
        StopWorkerOnRestartSignalListener $stopWorkerOnRestartSignalListener,
        StopWorkerOnSigtermSignalListener $stopWorkerOnSigtermSignalListener,
        DispatchPcntlSignalListener $dispatchPcntlSignalListener,
        EarlyReturnMessagesListener $earlyReturnListener,
        string $defaultTransportName
    ) {
        $this->receiverLocator = $receiverLocator;
        $this->bus = $bus;
        $this->pollInterval = $pollInterval;
        $this->stopWorkerOnRestartSignalListener = $stopWorkerOnRestartSignalListener;
        $this->stopWorkerOnSigtermSignalListener = $stopWorkerOnSigtermSignalListener;
        $this->dispatchPcntlSignalListener = $dispatchPcntlSignalListener;
        $this->earlyReturnListener = $earlyReturnListener;
        $this->defaultTransportName = $defaultTransportName;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Post(
     *     path="/_action/message-queue/consume",
     *     summary="Consume messages from the message queue.",
     *     description="This route can be used to consume messenges from the message queue. It is intended to be used if
no cronjob is configured to consume messages regulary.",
     *     operationId="consumeMessages",
     *     tags={"Admin API", "System Operations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"receiver"},
     *             @OA\Property(
     *                 property="receiver",
     *                 description="The name of the transport in the messenger that should be processed.
See the [Symfony Messenger documentation](https://symfony.com/doc/current/messenger.html) for more information",
     *                 type="string"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns information about handled messages",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="handledMessages",
     *                  description="The number of messages processed.",
     *                  type="integer"
     *              )
     *         )
     *     )
     * )
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
        $listener = new CountHandledMessagesListener($this->pollInterval);
        $workerDispatcher->addSubscriber($listener);
        $workerDispatcher->addSubscriber($this->stopWorkerOnRestartSignalListener);
        $workerDispatcher->addSubscriber($this->stopWorkerOnSigtermSignalListener);
        $workerDispatcher->addSubscriber($this->dispatchPcntlSignalListener);
        $workerDispatcher->addSubscriber($this->earlyReturnListener);

        $worker = new Worker([$this->defaultTransportName => $receiver], $this->bus, $workerDispatcher);

        $worker->run(['sleep' => 50]);

        return $this->json(['handledMessages' => $listener->getHandledMessages()]);
    }
}
