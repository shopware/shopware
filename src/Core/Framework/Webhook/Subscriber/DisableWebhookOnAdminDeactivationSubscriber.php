<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointLifecycle;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Routes the admin deactivate gesture — `PATCH /api/webhook/{id}` with `active = false` — through the
 * health model so an operator kill lands as DISABLED with `disabled_origin = operator`, not merely as
 * `active = 0` over stale health where recovery would silently resurrect it. The echo guard lives in
 * {@see EndpointLifecycle::disableByOperator}: a write that merely repeats the mirrored value (a
 * full-entity round-trip while suspended) is a no-op. The BC mirror's own `webhook.active` writes use
 * raw SQL and never fire `webhook.written`, so only an operator/API DAL write reaches this subscriber.
 *
 * @internal
 */
#[Package('framework')]
class DisableWebhookOnAdminDeactivationSubscriber implements EventSubscriberInterface
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
            // Only an update can be an operator deactivation; a freshly inserted webhook is HEALTHY by default.
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $payload = $writeResult->getPayload();
            $id = $payload['id'] ?? null;

            if (($payload['active'] ?? null) === false && \is_string($id)) {
                $this->endpointLifecycle->disableByOperator($id);
            }
        }
    }
}
