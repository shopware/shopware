<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Telemetry;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Pins the telemetry contract surface as the container compiles it: the core metric names, each metric's
 * label names, and the core mapping names. These are a part of the BC promise (after stabilization) and change
 * only in majors (see `adr/2026-10-19-telemetry-label-extensibility.md`, "The names become contract").
 * Nothing else enforces that promise - the BC checker covers PHP only, and the config tree
 * accepts any definition. If this test fails - there is a contract change that needs to go through deprecation cycle.
 *
 * The compiled container is read, only the breaking direction is gated - removal, rename, or a changed label
 * set of a pinned name. Additions are tolerated: they are minor-safe, downstream pipelines run this suite
 * with extra bundles installed, and local checkouts may carry custom `config/packages/` entries.
 * The per-label mechanism (allow list or mapping) and the shipped values are deliberately not pinned;
 * both may change in minors.
 *
 * @internal
 */
#[Package('framework')]
class TelemetryMetricContractTest extends TestCase
{
    use KernelTestBehaviour;

    private const METRIC_LABELS = [
        'api.sync.duration' => ['indexing_behavior', 'result'],
        'api.sync.entities.affected' => ['action', 'entity_group'],
        'api.sync.operations.count' => [],
        'app.install.count' => [],
        'cache.invalidate.count' => [],
        'cart.calculation.duration' => ['has_promotions', 'result', 'sales_channel_type'],
        'cart.errors.count' => [],
        'cart.line_items.count' => ['sales_channel_type'],
        'dal.associations.count' => [],
        'dal.search.duration' => ['backend', 'entity_group', 'es_aware', 'operation'],
        'database.locks.count' => [],
        'flow.execution.duration' => ['result', 'trigger_group'],
        'http.server.request.duration' => ['area', 'domain', 'operation', 'status_class'],
        'http.server.request.memory.peak' => ['area', 'domain', 'operation'],
        'http.server.request.queries.count' => ['area', 'domain', 'operation'],
        'indexer.batch.size' => ['indexer', 'mode'],
        'indexer.run.duration' => ['indexer', 'mode', 'result'],
        'mail.send.count' => ['mail_group', 'result'],
        'mail.send.duration' => ['result'],
        'messenger.message.handled.count' => ['message_group', 'result'],
        'messenger.message.handling.duration' => ['message_group', 'result'],
        'messenger.message.size' => [],
        'messenger.queue.depth' => ['transport'],
        'number_range.allocation.duration' => ['number_range_type', 'result', 'storage'],
        'order.line_items.count' => ['sales_channel_type'],
        'order.placed.count' => ['payment_method', 'sales_channel_type'],
        'plugin.install.count' => [],
        'scheduled_task.backlog.max_lateness_seconds' => [],
        'scheduled_task.failed.count' => [],
        'scheduled_task.run.duration' => ['result', 'task_name'],
    ];

    private const MAPPING_NAMES = [
        'action_domain',
        'entity_group',
        'flow_trigger_group',
        'mail_group',
        'message_group',
        'number_range_type',
        'route_domain',
        'sales_channel_type',
        'task_name',
    ];

    public function testPinnedMetricNamesKeepTheirLabelSets(): void
    {
        $definitions = $this->containerParameter('shopware.telemetry.metrics.definitions');

        foreach (self::METRIC_LABELS as $metricName => $expectedLabels) {
            static::assertArrayHasKey(
                $metricName,
                $definitions,
                \sprintf('The pinned metric "%s" is gone from the compiled configuration. Metric names are telemetry contract surface (see the class docblock): removal or rename needs a deprecation cycle and a deliberate snapshot update.', $metricName)
            );

            $definition = $definitions[$metricName];
            static::assertIsArray($definition);
            $labels = $definition['labels'] ?? [];
            static::assertIsArray($labels);

            $labelNames = array_keys($labels);
            sort($labelNames);

            static::assertSame(
                $expectedLabels,
                $labelNames,
                \sprintf('The label set of the pinned metric "%s" changed. Label names are telemetry contract surface (see the class docblock): update the snapshot deliberately.', $metricName)
            );
        }
    }

    public function testPinnedMappingNamesStillExist(): void
    {
        $mappings = $this->containerParameter('shopware.telemetry.metrics.mappings');

        foreach (self::MAPPING_NAMES as $mappingName) {
            static::assertArrayHasKey(
                $mappingName,
                $mappings,
                \sprintf('The pinned mapping "%s" is gone from the compiled configuration. Mapping names are telemetry contract surface (see the class docblock): removal or rename needs a deprecation cycle and a deliberate snapshot update.', $mappingName)
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function containerParameter(string $name): array
    {
        $value = static::getContainer()->getParameter($name);
        static::assertIsArray($value);

        return $value;
    }
}
