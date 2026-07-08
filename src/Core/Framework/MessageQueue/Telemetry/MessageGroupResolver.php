<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Buckets a messenger message class into a small, bounded set of groups, so the large, plugin-extensible
 * set of message classes does not blow up the metric label cardinality.
 *
 * Classification prefers the two structural families that have a stable base class — scheduled tasks
 * ({@see ScheduledTask}) and DAL indexing ({@see EntityIndexingMessage}) — resolved with `is_a()` so that
 * plugin messages extending them are grouped correctly for free. Everything else is matched by namespace
 * fragment. Results are memoized per class name: workers are long-lived, so each distinct message class
 * resolves once per process.
 *
 * The structural `is_a()` checks win, so a message that is an indexing message or scheduled task is grouped by that
 * mechanism even when its namespace points at a domain (e.g. `MediaIndexingMessage` is `indexer`, not `business`);
 * `other` then also collects framework-external and plugin messages that match no fragment.
 *
 * Owns its bounded output set (`other` as default), so the consuming metric label may use `policy: open`.
 * Known outputs: indexer, webhook, scheduled-task, mail, business, system, other.
 *
 * The hardcoded maps are intentional (optimized for deletion): while the label set is still changing, one
 * map with no extension API is simpler to maintain. Once the groups are stable we can switch to a cleaner
 * approach, e.g. a telemetry-group attribute on the message class.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class MessageGroupResolver
{
    /**
     * Namespace fragment → group, first match wins. Applied only after the structural `is_a()` checks, so
     * scheduled tasks and DAL indexing messages living in these namespaces are already classified and
     * cannot be mislabeled here (e.g. `Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask` is a
     * `ScheduledTask` and never reaches the `Framework\Webhook\` webhook fragment).
     *
     * @var array<string, string>
     */
    private const NAMESPACE_GROUPS = [
        // indexer control + Elasticsearch indexing messages that don't extend EntityIndexingMessage
        // (matched by string so Framework must not depend on the optional Elasticsearch bundle)
        'DataAbstractionLayer\\Indexing\\MessageQueue\\' => 'indexer',
        'Elasticsearch\\Framework\\Indexing\\' => 'indexer',
        'Elasticsearch\\Admin\\' => 'indexer',

        // webhook delivery
        'Framework\\Webhook\\' => 'webhook',

        // mail — Shopware (SendMailMessage) and Symfony mailer transport
        'Content\\Mail\\' => 'mail',
        'Mailer\\Messenger\\' => 'mail',

        // business — customer-/catalog-facing async work
        'Content\\Media\\Message\\' => 'business',
        'Content\\ImportExport\\' => 'business',
        'Content\\ProductExport\\' => 'business',
        'Content\\Sitemap\\' => 'business',

        // system — framework, infrastructure & housekeeping
        'Framework\\Adapter\\Cache\\' => 'system',
        'Framework\\App\\Message\\' => 'system',
        'Storefront\\Theme\\' => 'system', // theme compilation
        'MessageQueue\\ScheduledTask\\Register' => 'system',
        'Service\\Message\\' => 'system',
        'System\\UsageData\\' => 'system',
    ];

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function resolve(string $messageClass): string
    {
        return $this->cache[$messageClass] ??= $this->classify($messageClass);
    }

    private function classify(string $messageClass): string
    {
        if (is_a($messageClass, ScheduledTask::class, true)) {
            return 'scheduled-task';
        }

        if (is_a($messageClass, EntityIndexingMessage::class, true)) {
            return 'indexer';
        }

        foreach (self::NAMESPACE_GROUPS as $fragment => $group) {
            if (str_contains($messageClass, $fragment)) {
                return $group;
            }
        }

        return 'other';
    }
}
