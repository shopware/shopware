<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Event\WebhookActivationTrigger;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Routes the admin reactivate gesture — `PATCH /api/webhook/{id}` with `active = true` — through the
 * health model so a SUSPENDED/DISABLED webhook is reset to HEALTHY (counters cleared, held backlog
 * resumed, audit event emitted), not merely flipped `active = 1` over stale health. This is the
 * manual recovery path while the flag is on. `reactivate()` is a no-op for an already-HEALTHY webhook,
 * so an unrelated edit that happens to include `active = true` costs nothing. A bare secret rotation
 * emits no such write, so it stays a known gap (recover via this PATCH or an app install/update).
 *
 * @internal
 */
#[Package('framework')]
class ReactivateWebhookOnActivationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EndpointLifecycle $endpointLifecycle,
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
            // Only an update can be a reactivation; a freshly inserted webhook is HEALTHY by default.
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $payload = $writeResult->getPayload();
            $id = $payload['id'] ?? null;

            if (($payload['active'] ?? null) === true && \is_string($id)) {
                $this->endpointLifecycle->reactivate($id, WebhookActivationTrigger::Manual);
            }
        }
    }
}
