<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Handler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RetryMessageHandler extends AbstractMessageHandler
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var EntityRepositoryInterface
     */
    private $deadMessageRepository;

    public function __construct(ContainerInterface $container, EntityRepositoryInterface $deadMessageRepository)
    {
        $this->container = $container;
        $this->deadMessageRepository = $deadMessageRepository;
    }

    /**
     * @param RetryMessage $message
     */
    public function handle($message): void
    {
        /** @var DeadMessageEntity|null $deadMessage */
        $deadMessage = $this->deadMessageRepository
            ->search(new Criteria([$message->getDeadMessageId()]), Context::createDefaultContext())
            ->get($message->getDeadMessageId());

        if (!$deadMessage) {
            return;
        }

        /** @var AbstractMessageHandler $handler */
        $handler = $this->container->get($deadMessage->getHandlerClass());
        $handler($deadMessage->getOriginalMessage());

        $this->deadMessageRepository->delete([
            [
                'id' => $deadMessage->getId(),
            ],
        ], Context::createDefaultContext());
    }

    public static function getHandledMessages(): iterable
    {
        return [RetryMessage::class];
    }
}
