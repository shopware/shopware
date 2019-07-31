<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
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

    /**
     * @var int
     */
    private $pollInterval;

    public function __construct(ServiceLocator $receiverLocator, MessageBusInterface $bus, int $pollInterval)
    {
        $this->receiverLocator = $receiverLocator;
        $this->bus = $bus;
        $this->pollInterval = $pollInterval;
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

        $worker = new Worker([$receiver], $this->bus);

        $handledMessages = 0;
        $started = (new \DateTimeImmutable())->getTimestamp();
        $worker->run([], function (?Envelope $envelope) use ($worker, $started, &$handledMessages) {
            if ($envelope !== null) {
                ++$handledMessages;
            }

            if ($started + $this->pollInterval < (new \DateTimeImmutable())->getTimestamp()) {
                $worker->stop();
            }
        });

        return $this->json(['handledMessages' => $handledMessages]);
    }
}
