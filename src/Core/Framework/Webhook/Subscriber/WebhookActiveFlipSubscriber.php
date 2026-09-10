<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An admin write that flips `active` carries operator intent in either direction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber\DisableWebhookOnAdminDeactivationSubscriberTest
 * @see \Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber\ReactivateWebhookOnActivationSubscriberTest
 */
#[Package('framework')]
class WebhookActiveFlipSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WebhookHealthService $healthService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookDefinition::ENTITY_NAME . '.written' => 'onWebhookWritten',
        ];
    }

    public function onWebhookWritten(EntityWrittenEvent $event): void
    {
        if (!Feature::isActive('WEBHOOKS_REWORK')) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $payload = $writeResult->getPayload();
            $active = $payload['active'] ?? null;
            $webhookId = $payload['id'] ?? null;
            if (!\is_bool($active) || !\is_string($webhookId)) {
                continue;
            }

            if ($active) {
                $this->healthService->reactivate($webhookId, WebhookActivationTrigger::Manual);

                continue;
            }

            $this->healthService->disableByOperatorOnActiveFlip($webhookId);
        }
    }
}
