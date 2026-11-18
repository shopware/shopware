<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Hookable;

/**
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
        public ?string $webhookName,
        public ?string $eventName,
        public \DateTimeImmutable $occurredAt,
        public ?\DateTimeImmutable $clearedSuspendedSince = null,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
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
            ->add('clearedSuspendedSince', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('webhookName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('eventName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('occurredAt', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
