<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\MessageHandler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\Message;
use Shopware\Core\Framework\MessageQueue\MessageQueueSizeEntity;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

abstract class AbstractMessageHandler implements MessageSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $messageQueueSizeRepository;

    public function __construct(EntityRepositoryInterface $messageQueueSizeRepository)
    {
        $this->messageQueueSizeRepository = $messageQueueSizeRepository;
    }

    public function __invoke(Message $msg)
    {
        $this->decrementMessageQueueSize($msg);
        $this->handle($msg);
    }

    abstract public static function getHandledMessages(): iterable;

    abstract protected function handle(Message $msg): void;

    protected function decrementMessageQueueSize(Message $msg): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $msg->getEventName()));
        /** @var MessageQueueSizeEntity|null $queueSize */
        $queueSize = $this->messageQueueSizeRepository->search($criteria, $context)->first();

        if (!$queueSize) {
            return;
        }

        $this->messageQueueSizeRepository->update([
            [
                'id' => $queueSize->getId(),
                'size' => $queueSize->getSize() - 1,
            ],
        ], $context);
    }
}
