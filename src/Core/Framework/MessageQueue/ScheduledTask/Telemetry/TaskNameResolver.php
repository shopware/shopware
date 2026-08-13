<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Passes a core scheduled-task name through as a metric label value; anything outside the allowlist
 * (plugin/app tasks) collapses to `other`, bounding the label cardinality.
 *
 * Owns its bounded output set (closed allowlist, `other` as default), so the consuming metric label may
 * use `policy: open`. The hardcoded list is intentional — see the rationale on
 * {@see \Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class TaskNameResolver
{
    /**
     * All core task names ({@see \Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask::getTaskName()}
     * of every core task class), as a hash set for O(1) lookup.
     *
     * @var array<string, true>
     */
    private const CORE_TASKS = [
        'app.system_heartbeat' => true,
        'app_delete' => true,
        'app_update' => true,
        'cart.cleanup' => true,
        'customer.cleanup_customer_recovery' => true,
        'customer.delete_unused_guests' => true,
        'delete_newsletter_recipient_task' => true,
        'import_export_file.cleanup' => true,
        'in-app-purchase.update' => true,
        'log_entry.cleanup' => true,
        'media.cleanup_corrupted_media' => true,
        'payment_token.cleanup' => true,
        'product_download.media.cleanup' => true,
        'product_export_generate_task' => true,
        'product_keyword_dictionary.cleanup' => true,
        'product_stream.mapping.update' => true,
        'sales_channel_context.cleanup' => true,
        'services.install' => true,
        'shopware.elasticsearch.create.alias' => true,
        'shopware.invalidate_cache' => true,
        'shopware.sitemap_generate' => true,
        'telemetry.collect_periodic_metrics' => true,
        'theme.delete_files' => true,
        'usage_data.entity_data.collect' => true,
        'version.cleanup' => true,
        'webhook_event_log.cleanup' => true,
    ];

    public function resolve(string $taskName): string
    {
        return isset(self::CORE_TASKS[$taskName]) ? $taskName : 'other';
    }
}
