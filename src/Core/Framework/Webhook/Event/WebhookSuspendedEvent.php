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
