<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncDispatcher
{
    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var EntityRepositoryInterface
     */
    private $messageQueueSizeRepository;

    public function __construct(
        MessageBusInterface $messageBus,
        EntityRepositoryInterface $messageQueueSizeRepository
    ) {
        $this->messageBus = $messageBus;
        $this->messageQueueSizeRepository = $messageQueueSizeRepository;
    }

    public function dispatch(string $eventName, Message $msg): Message
    {
        $this->incrementMessageQueueSize($eventName);
        $msg->setEventName($eventName);
        $this->messageBus->dispatch($msg);

        return $msg;
    }

    protected function incrementMessageQueueSize(string $eventName)
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $eventName));
        /** @var MessageQueueSizeEntity|null $queueSize */
        $queueSize = $this->messageQueueSizeRepository->search($criteria, $context)->first();

        if (!$queueSize) {
            $this->messageQueueSizeRepository->create([
                [
                    'name' => $eventName,
                    'size' => 1,
                ],
            ], $context);

            return;
        }

        $this->messageQueueSizeRepository->update([
            [
                'id' => $queueSize->getId(),
                'size' => $queueSize->getSize() + 1,
            ],
        ], $context);
    }
}
