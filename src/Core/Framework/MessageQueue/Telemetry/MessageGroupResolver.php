<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolves a messenger message FQCN to the `message_group` label value
 * (see `metrics.mappings.message_group` in `telemetry.yaml`, extension and merge rules in
 * `src/Core/Framework/Telemetry/README.md`).
 *
 * The core grouping stays in PHP: scheduled tasks ({@see ScheduledTask}) and DAL
 * indexing ({@see EntityIndexingMessage}) resolve via `is_a()` so plugin subclasses group with it,
 * {@see MessageGroupResolver::NAMESPACE_GROUPS} are matched by fragment - not expressible as prefix keys.
 *
 * Configuration entry still beats these rules.
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
    final public const MAPPING = 'message_group';

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

    public function __construct(private readonly LabelMappingRegistry $mappings)
    {
    }

    public function resolve(string $messageClass): string
    {
        return $this->mappings->resolve(self::MAPPING, $messageClass, $this->classify(...));
    }

    private function classify(string $messageClass): ?string
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

        return null;
    }
}
