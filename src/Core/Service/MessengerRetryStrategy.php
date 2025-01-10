<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('core')]
class MessengerRetryStrategy implements RetryStrategyInterface, ResetInterface
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
        0 => 5000,          // 5 seconds
        1 => 600000,        // 10 minutes
        2 => 3600000,       // 1 hour
        3 => 21600000,      // 6 hours
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly RetryStrategyInterface $decoratedRetryStrategy,
        private readonly Connection $connection
    ) {
    }

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        $realMessage = $message->getMessage();

        if (!$this->isRequiredSelfManagedWebHook($realMessage)) {
            return $this->decoratedRetryStrategy->isRetryable($message, $throwable);
        }

        return true;
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        if (!$this->isRequiredSelfManagedWebHook($message->getMessage())) {
            return $this->decoratedRetryStrategy->getWaitingTime($message, $throwable);
        }

        $retries = RedeliveryStamp::getRetryCountFromEnvelope($message);

        if ($retries <= \count($this->delays)) {
            return $this->delays[$retries];
        }

        // for further retries, try with + 1 day
        return self::ONE_DAY * ($retries - \count($this->delays));
    }

    public function reset(): void
    {
        $this->appManagedCache = [];
    }

    private function isRequiredSelfManagedWebHook(object $message): bool
    {
        if (!$message instanceof WebhookEventMessage) {
            return false;
        }

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
