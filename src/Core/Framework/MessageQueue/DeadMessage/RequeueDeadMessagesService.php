<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\DeadMessage;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class RequeueDeadMessagesService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    /**
     * @var MessageBusInterface
     */
    private $encryptedBus;

    public function __construct(
        EntityRepositoryInterface $deadMessageRepository,
        MessageBusInterface $bus,
        MessageBusInterface $encryptedBus
    ) {
        $this->deadMessageRepository = $deadMessageRepository;
        $this->bus = $bus;
        $this->encryptedBus = $encryptedBus;
    }

    public function requeue(?string $messageClass = null): void
    {
        $criteria = $this->buildCriteria($messageClass);
        $context = Context::createDefaultContext();
        $messages = $this->deadMessageRepository->search($criteria, $context)->getEntities();

        $notFoundDeadMessages = [];

        /** @var DeadMessageEntity $message */
        foreach ($messages as $message) {
            if (!class_exists($message->getOriginalMessageClass())) {
                $notFoundDeadMessages[] = ['id' => $message->getId()];

                continue;
            }
            $this->dispatchRetryMessage($message);
        }

        if (!empty($notFoundDeadMessages)) {
            $this->deadMessageRepository->delete($notFoundDeadMessages, $context);
        }
    }

    private function buildCriteria(?string $messageClass): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter(
            'nextExecutionTime',
            [
                RangeFilter::LT => (new \DateTime())->format(\DATE_ATOM),
            ]
        ));

        if ($messageClass) {
            $criteria->addFilter(new EqualsFilter('originalMessageClass', $messageClass));
        }

        return $criteria;
    }

    private function dispatchRetryMessage(DeadMessageEntity $message): void
    {
        $retryMessage = new RetryMessage($message->getId());

        if ($message->isEncrypted()) {
            $this->encryptedBus->dispatch($retryMessage);
        } else {
            $this->bus->dispatch($retryMessage);
        }
    }
}
