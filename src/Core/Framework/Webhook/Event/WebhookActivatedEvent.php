<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * A webhook reached HEALTHY, by any path — the trigger says which one. Best-effort and
 * post-commit: the event is advisory only, the `webhook_health` row is the truth, and a
 * listener failure never affects the transition. `clearedSuspendedSince` is non-null when
 * this recovery ends a suspension episode (it carries the value the transition cleared);
 * it is the key for the Admin recovery notice.
 *
 * @internal
 */
#[Package('framework')]
final readonly class WebhookActivatedEvent implements Hookable, FlowEventAware
{
    use WebhookHealthEventBehaviour;

    public const NAME = 'webhook.health.activated';

    public function __construct(
        public string $webhookId,
        public ?string $appId,
        public EndpointState $fromState,
        public WebhookActivationTrigger $trigger,
        public ?\DateTimeImmutable $clearedSuspendedSince = null,
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
            'trigger' => $this->trigger->value,
            'clearedSuspendedSince' => $this->getClearedSuspendedSince(),
        ];
    }

    public function getTrigger(): string
    {
        return $this->trigger->value;
    }

    public function getClearedSuspendedSince(): ?string
    {
        return $this->clearedSuspendedSince?->format(\DateTimeInterface::ATOM);
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('webhookId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('fromState', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('trigger', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('clearedSuspendedSince', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
