<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\DisabledOrigin;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * A webhook entered DISABLED — by the 7-day escalation or an operator kill; `origin` says which.
 * Best-effort and post-commit: advisory only, the `webhook_health` row is the truth, and a listener
 * failure never affects the transition. Entering DISABLED always notifies the Admin.
 * `webhookName`/`eventName` are null only when the webhook row vanished between the transition and
 * the emission lookup.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookDisabledEvent implements Hookable, FlowEventAware
{
    use WebhookHealthEventBehaviour;

    public const NAME = 'webhook.health.disabled';

    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public DisabledOrigin $origin,
        public ?string $webhookName,
        public ?string $eventName,
        public \DateTimeImmutable $occurredAt,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Ids and state only. Never the endpoint URL, headers, or a delivery payload.
     *
     * @return array<string, mixed>
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'webhookId' => $this->webhookId,
            'fromState' => $this->fromState->value,
            'origin' => $this->origin->value,
            'webhookName' => $this->webhookName,
            'eventName' => $this->eventName,
            'occurredAt' => $this->getOccurredAt(),
        ];
    }

    public function getOrigin(): string
    {
        return $this->origin->value;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('webhookId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('fromState', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('origin', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('webhookName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('eventName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('occurredAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
