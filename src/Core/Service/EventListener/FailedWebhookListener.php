<?php declare(strict_types=1);

namespace Shopware\Core\Service\EventListener;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Webhook\Event\WebhookFailedEvent;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class FailedWebhookListener implements ResetInterface
{
    private const REQUIRED_EVENTS = [
        UpdatePostFinishEvent::EVENT_NAME,
    ];

    private const ONE_DAY = 86400000; // ms

    /**
     * @var array<string, bool>
     */
    private array $appManagedCache = [];

    /**
     * @var array<int, int>
     */
    private array $delays = [
        1 => 5000,          // 5 seconds
        2 => 600000,        // 10 minutes
        3 => 3600000,       // 1 hour
        4 => 21600000,      // 6 hours
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(WebhookFailedEvent $event): void
    {
        if (!$this->isRequiredSelfManagedWebHook($event->message)) {
            return;
        }

        if ($event->numFails <= \count($this->delays)) {
            $delay = $this->delays[$event->numFails];
        } else {
            $delay = self::ONE_DAY * ($event->numFails - \count($this->delays));
        }

        throw new RecoverableMessageHandlingException(
            message: $event->exception->getMessage(),
            previous: $event->exception,
            retryDelay: $delay
        );
    }

    public function reset(): void
    {
        $this->appManagedCache = [];
    }

    private function isRequiredSelfManagedWebHook(WebhookEventMessage $message): bool
    {
        $payload = $message->getPayload();

        if (!\in_array($payload['data']['event'] ?? null, self::REQUIRED_EVENTS, true)) {
            // it's an event we don't care about
            return false;
        }

        $appId = $message->getAppId();

        if (!$appId) {
            return false;
        }

        return $this->isAppSelfManaged($appId);
    }

    private function isAppSelfManaged(string $appId): bool
    {
        if (isset($this->appManagedCache[$appId])) {
            return $this->appManagedCache[$appId];
        }

        $selfManaged = $this->connection->fetchOne('SELECT self_managed FROM app WHERE id = UNHEX(:id)', ['id' => $appId]);

        return $this->appManagedCache[$appId] = (bool) $selfManaged;
    }
}
