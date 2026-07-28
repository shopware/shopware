<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Buckets a messenger message class into a small, bounded set of groups, so the large, plugin-extensible
 * set of message classes does not blow up the metric label cardinality.
 *
 * Owns its bounded output set (`other` as default), so the consuming metric label may use `policy: open`.
 * Known outputs: indexer, webhook, scheduled-task, mail, business, system, other.
 *
 * The hardcoded maps are intentional (optimized for deletion). Once the groups are stable we can switch to a cleaner
 * approach, e.g. a telemetry-group attribute on the message class.
 *
 * Classification runs in order: the two structural families with a stable base class first — scheduled
 * tasks ({@see ScheduledTask}) and DAL indexing ({@see EntityIndexingMessage}), resolved via `is_a()` so
 * plugin subclasses group for free — then the {@see NAMESPACE_GROUPS} fragment map, else `other`. The
 * structural checks win, so such a message is grouped by them even when its namespace points at another
 * domain. Results are memoized per class name; workers are long-lived, so each class resolves once.
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
     * Namespace fragment → group, first match wins (see class docblock for the classification logic and priority).
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

        // system — event-driven framework/infra housekeeping
        // note that periodic cache invalidations are a `scheduled-task`
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
