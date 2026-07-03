<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\SuspensionCause;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * A webhook entered SUSPENDED. Best-effort and post-commit: advisory only, the `webhook_health` row
 * is the truth, and a listener failure never affects the transition. `suspendedSince` is the
 * episode anchor — set once on the first suspension and unchanged across re-suspension — and keys
 * the one-notification-per-suspension rule; `occurredAt` is this transition's own time (on
 * re-suspension the two differ). `webhookName`/`eventName` are null only when the webhook row
 * vanished between the transition and the emission lookup.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookSuspendedEvent implements Hookable, FlowEventAware
{
    use WebhookHealthEventBehaviour;

    public const NAME = 'webhook.health.suspended';

    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public \DateTimeImmutable $suspendedSince,
        public SuspensionCause $cause,
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
     * Ids, names, coarse state/cause enums, and timestamps only. Never the endpoint URL,
     * headers, or a delivery payload. `suspendedSince` is the anchor the app vendor
     * reconciles against (it also appears on `GET /state`); `webhookName` is the key into
     * `GET /state` and `POST /reactivate`; `cause` names the remedy class.
     *
     * @return array<string, mixed>
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'webhookId' => $this->webhookId,
            'fromState' => $this->fromState->value,
            'suspendedSince' => $this->getSuspendedSince(),
            'cause' => $this->cause->value,
            'webhookName' => $this->webhookName,
            'eventName' => $this->eventName,
            'occurredAt' => $this->getOccurredAt(),
        ];
    }

    public function getCause(): string
    {
        return $this->cause->value;
    }

    public function getSuspendedSince(): string
    {
        return $this->suspendedSince->format(\DateTimeInterface::ATOM);
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('webhookId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('fromState', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('suspendedSince', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('cause', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('webhookName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('eventName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('occurredAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
