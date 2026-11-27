<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;

/**
 * Reports per-app health from signals the shop already stores. A stepping stone until the webhook health
 * rework (#16565) provides proper delivery signals. A changed shop identity is reported by ShopIdCheck.
 *
 * @internal
 */
#[Package('framework')]
class AppSystemCheck extends BaseCheck
{
    final public const REASON_INACTIVE = 'inactive';
    final public const REASON_REGISTRATION_INCOMPLETE = 'registration_incomplete';
    final public const REASON_CREDENTIALS_REJECTED = 'credentials_rejected';
    final public const REASON_DELIVERY_FAILING = 'delivery_failing';
    final public const REASON_WEBHOOKS_SUSPENDED = 'webhooks_suspended';
    final public const REASON_NO_APP_ACTIVITY = 'no_app_activity';
    final public const REASON_NO_SIGNAL = 'no_signal';

    // a healthy registration confirm is in flight for seconds; unconfirmed secrets older than this are stuck
    private const STUCK_REGISTRATION_AFTER = '-5 minutes';

    private const NO_APP_ACTIVITY_AFTER = '-7 days';

    private const CREDENTIALS_REJECTED_STATUS_CODES = [401, 403];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function run(): Result
    {
        $apps = $this->evaluate();
        $status = self::worstStatus($apps);

        return new Result(
            name: $this->name(),
            status: $status,
            message: match ($status) {
                Status::ERROR => 'At least one app cannot communicate with its app server',
                Status::WARNING => 'At least one app shows signs of failing communication',
                default => 'No app shows signs of failing communication',
            },
            healthy: $status !== Status::ERROR,
            extra: ['apps' => array_map(static fn (array $app) => ['status' => $app['status']->name] + $app, $apps)],
        );
    }

    public function category(): Category
    {
        return Category::EXTERNAL;
    }

    public function name(): string
    {
        return 'AppSystem';
    }

    public function tags(): array
    {
        return ['apps'];
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return [
            SystemCheckExecutionContext::WEB,
            SystemCheckExecutionContext::CLI,
            SystemCheckExecutionContext::RECURRENT,
        ];
    }

    /**
     * @return list<array{name: string, status: Status, reason: string|null, lastStatusCode: int|null}>
     */
    private function evaluate(): array
    {
        $webhooks = $this->fetchWebhookCounts();
        $retries = $this->fetchLatestRetries();

        return array_map(
            static fn (array $app) => self::toHealth($app, $webhooks[$app['id']] ?? [], $retries[$app['id']] ?? null),
            $this->fetchApps(),
        );
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $webhooks
     * @param array<string, mixed>|null $retry
     *
     * @return array{name: string, status: Status, reason: string|null, lastStatusCode: int|null}
     */
    private static function toHealth(array $app, array $webhooks, ?array $retry): array
    {
        $lastStatusCode = isset($retry['response_status_code']) ? (int) $retry['response_status_code'] : null;

        [$status, $reason] = match (true) {
            !$app['active'] => [Status::SKIPPED, self::REASON_INACTIVE],
            (bool) $app['registration_stuck'] => [Status::ERROR, self::REASON_REGISTRATION_INCOMPLETE],
            \in_array($lastStatusCode, self::CREDENTIALS_REJECTED_STATUS_CODES, true) => [Status::ERROR, self::REASON_CREDENTIALS_REJECTED],
            $retry !== null || (int) ($webhooks['failing'] ?? 0) > 0 => [Status::WARNING, self::REASON_DELIVERY_FAILING],
            (int) ($webhooks['suspended'] ?? 0) > 0 => [Status::WARNING, self::REASON_WEBHOOKS_SUSPENDED],
            (bool) $app['no_recent_activity'] => [Status::WARNING, self::REASON_NO_APP_ACTIVITY],
            (int) ($webhooks['total'] ?? 0) === 0 && $app['last_usage_at'] === null => [Status::UNKNOWN, self::REASON_NO_SIGNAL],
            default => [Status::OK, null],
        };

        return ['name' => (string) $app['name'], 'status' => $status, 'reason' => $reason, 'lastStatusCode' => $lastStatusCode];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchApps(): array
    {
        // every installed app with its registration state and when its integration last requested an Admin API token
        return $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(app.id)) AS id,
                    app.name,
                    app.active,
                    app.app_secret IS NOT NULL AS registered,
                    COALESCE(app.unconfirmed_app_secrets IS NOT NULL AND COALESCE(app.updated_at, app.created_at) < :stuckBefore, 0) AS registration_stuck,
                    COALESCE(integration.last_usage_at < :noActivityBefore, 0) AS no_recent_activity,
                    integration.last_usage_at
             FROM app
             INNER JOIN integration ON integration.id = app.integration_id',
            [
                'stuckBefore' => $this->dateTime(self::STUCK_REGISTRATION_AFTER),
                'noActivityBefore' => $this->dateTime(self::NO_APP_ACTIVITY_AFTER),
            ]
        );
    }

    /**
     * @return array<string, array<string, mixed>> keyed by app id
     */
    private function fetchWebhookCounts(): array
    {
        // per app: how many webhooks it has, how many are failing and how many are suspended
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(app_id)) AS app_id,
                    COUNT(*) AS total,
                    SUM(active = 1 AND error_count > 0) AS failing,
                    SUM(active = 0) AS suspended
             FROM webhook
             WHERE app_id IS NOT NULL
             GROUP BY app_id'
        );
    }

    /**
     * @return array<string, array<string, mixed>> keyed by app id
     */
    private function fetchLatestRetries(): array
    {
        // per app: the most recently attempted delivery that is still waiting for a retry, with its response status code
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT latest.app_id, webhook_event_log.response_status_code
             FROM (
                 SELECT LOWER(HEX(webhook.app_id)) AS app_id,
                        webhook_delivery.webhook_event_log_id,
                        webhook_delivery.last_attempt_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY webhook.app_id
                            ORDER BY webhook_delivery.last_attempt_at DESC, webhook_delivery.id DESC
                        ) AS position
                 FROM webhook_delivery
                 INNER JOIN webhook ON webhook.id = webhook_delivery.webhook_id
                 WHERE webhook.app_id IS NOT NULL
                   AND webhook_delivery.execution_count >= 1
                   AND webhook_delivery.delivery_status IN (:retryingStatuses)
             ) latest
             INNER JOIN webhook_event_log ON webhook_event_log.id = latest.webhook_event_log_id
             WHERE latest.position = 1',
            ['retryingStatuses' => [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]],
            ['retryingStatuses' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param list<array{name: string, status: Status, reason: string|null, lastStatusCode: int|null}> $apps
     */
    private static function worstStatus(array $apps): Status
    {
        if ($apps === []) {
            return Status::OK;
        }

        return array_reduce(
            $apps,
            static fn (Status $worst, array $app) => self::severity($app['status']) > self::severity($worst) ? $app['status'] : $worst,
            Status::SKIPPED,
        );
    }

    private static function severity(Status $status): int
    {
        return match ($status) {
            Status::SKIPPED => 0,
            Status::OK => 1,
            Status::UNKNOWN => 2,
            Status::WARNING => 3,
            Status::ERROR => 4,
            Status::FAILURE => 5,
        };
    }

    private function dateTime(string $modifier): string
    {
        return $this->clock->now()->modify($modifier)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
