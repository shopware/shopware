<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Webhook\Event\RetryWebhookMessageFailedEvent;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RetryWebhookMessageFailedSubscriber implements EventSubscriberInterface
{
    private const MAX_WEBHOOK_ERROR_COUNT = 10;
    private const MAX_DEAD_MESSAGE_ERROR_COUNT = 2;

    private EntityRepositoryInterface $deadMessageRepository;

    private EntityRepositoryInterface $webhookRepository;

    private EntityRepositoryInterface $webhookEventLogRepository;

    public function __construct(
        EntityRepositoryInterface $deadMessageRepository,
        EntityRepositoryInterface $webhookRepository,
        EntityRepositoryInterface $webhookEventLogRepository
    ) {
        $this->deadMessageRepository = $deadMessageRepository;
        $this->webhookRepository = $webhookRepository;
        $this->webhookEventLogRepository = $webhookEventLogRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [RetryWebhookMessageFailedEvent::class => ['handleWebhookMessageFail']];
    }

    public function handleWebhookMessageFail(RetryWebhookMessageFailedEvent $event): void
    {
        $deadMessage = $event->getDeadMessage();
        $context = $event->getContext();

        if ($deadMessage->getErrorCount() < self::MAX_DEAD_MESSAGE_ERROR_COUNT) {
            return;
        }

        /** @var WebhookEventMessage $webhookEventMessage */
        $webhookEventMessage = $deadMessage->getOriginalMessage();

        $webhookId = $webhookEventMessage->getWebhookId();
        $webhookEventLogId = $webhookEventMessage->getWebhookEventId();

        $this->deleteDeadMessage($deadMessage->getId(), $context);
        $this->markWebhookEventFailed($webhookEventLogId, $context);

        /** @var WebhookEntity|null $webhook */
        $webhook = $this->webhookRepository
            ->search(new Criteria([$webhookId]), $context)
            ->get($webhookId);

        if ($webhook === null || !$webhook->isActive()) {
            return;
        }

        $webhookErrorCount = $webhook->getErrorCount() + 1;
        $params = [
            'id' => $webhook->getId(),
            'errorCount' => $webhookErrorCount,
        ];

        if ($webhookErrorCount >= self::MAX_WEBHOOK_ERROR_COUNT) {
            $params = array_merge($params, [
                'errorCount' => 0,
                'active' => false,
            ]);
        }

        $this->webhookRepository->update([$params], $context);
    }

    private function deleteDeadMessage(string $deadMessageId, Context $context): void
    {
        $this->deadMessageRepository->delete([
            [
                'id' => $deadMessageId,
            ],
        ], $context);
    }

    private function markWebhookEventFailed(string $webhookEventLogId, Context $context): void
    {
        $this->webhookEventLogRepository->update([
            [
                'id' => $webhookEventLogId,
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_FAILED,
            ],
        ], $context);
    }
}
