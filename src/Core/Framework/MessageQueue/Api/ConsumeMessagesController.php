<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Api;

use Shopware\Core\Framework\MessageQueue\Receiver\CountHandledMessagesReceiver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\StopWhenTimeLimitIsReachedReceiver;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Routing\Annotation\Route;

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

    public function __construct(ServiceLocator $receiverLocator, MessageBusInterface $bus)
    {
        $this->receiverLocator = $receiverLocator;
        $this->bus = $bus;
    }

    /**
     * @Route("/api/v{version}/_action/message-queue/consume", name="api.action.message-queue.consume", methods={"POST"})
     */
    public function consumeMessages(Request $request): JsonResponse
    {
        $receiverName = $request->get('receiver');

        if (!$receiverName || !$this->receiverLocator->has($receiverName)) {
            throw new \RuntimeException('No receiver name provided.');
        }

        $receiver = $this->receiverLocator->get($receiverName);
        $receiver = new StopWhenTimeLimitIsReachedReceiver($receiver, 2);
        $receiver = new CountHandledMessagesReceiver($receiver);

        $worker = new Worker($receiver, $this->bus);
        $worker->run();

        return $this->json(['handledMessages' => $receiver->getHandledMessagesCount()]);
    }
}
