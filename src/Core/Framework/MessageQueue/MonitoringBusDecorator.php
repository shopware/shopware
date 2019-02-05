<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class MonitoringBusDecorator implements MessageBusInterface
{
    /**
     * @var MessageBusInterface
     */
    private $innerBus;

    /**
     * @var EntityRepositoryInterface
     */
    private $messageQueueRepository;

    public function __construct(
        MessageBusInterface $inner,
        EntityRepositoryInterface $messageQueueRepository
    ) {
        $this->innerBus = $inner;
        $this->messageQueueRepository = $messageQueueRepository;
    }

    /**
     * Dispatches the given message to the inner Bus and Logs it.
     *
     * @param object|Envelope $message
     */
    public function dispatch($message): Envelope
    {
        $messageName = $this->getMessageName($message);

        if ($this->isIncoming($message)) {
            $this->decrementMessageQueueSize($messageName);
        } else {
            $this->incrementMessageQueueSize($messageName);
        }

        return $this->innerBus->dispatch($message);
    }

    /**
     * @param object|Envelope $message
     */
    private function isIncoming($message): bool
    {
        return $message instanceof Envelope && $message->all(ReceivedStamp::class);
    }

    /**
     * @param object|Envelope $message
     */
    private function getMessageName($message): string
    {
        return $message instanceof Envelope ? get_class($message->getMessage()) : get_class($message);
    }

    private function incrementMessageQueueSize(string $name)
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));
        /** @var ?MessageQueueStatsEntity $queueSize */
        $queueSize = $this->messageQueueRepository->search($criteria, $context)->first();

        if (!$queueSize) {
            $this->messageQueueRepository->create([
                [
                    'name' => $name,
                    'size' => 1,
                ],
            ], $context);

            return;
        }

        $this->messageQueueRepository->update([
            [
                'id' => $queueSize->getId(),
                'size' => $queueSize->getSize() + 1,
            ],
        ], $context);
    }

    private function decrementMessageQueueSize(string $name)
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $name));
        /** @var MessageQueueStatsEntity|null $queueSize */
        $queueSize = $this->messageQueueRepository->search($criteria, $context)->first();

        if (!$queueSize) {
            return;
        }

        $this->messageQueueRepository->update([
            [
                'id' => $queueSize->getId(),
                'size' => $queueSize->getSize() - 1,
            ],
        ], $context);
    }
}
