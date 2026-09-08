<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\TaskNameResolver;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TaskNameResolver::class)]
class TaskNameResolverTest extends TestCase
{
    #[DataProvider('taskNameProvider')]
    public function testResolve(string $taskName, string $expected): void
    {
        static::assertSame($expected, (new TaskNameResolver())->resolve($taskName));
    }

    public static function taskNameProvider(): \Generator
    {
        // core task names pass through unchanged (closed allowlist)
        yield 'version.cleanup passes through' => ['version.cleanup', 'version.cleanup'];
        yield 'shopware.invalidate_cache passes through' => ['shopware.invalidate_cache', 'shopware.invalidate_cache'];
        yield 'theme.delete_files passes through' => ['theme.delete_files', 'theme.delete_files'];
        yield 'telemetry.collect_periodic_metrics passes through' => ['telemetry.collect_periodic_metrics', 'telemetry.collect_periodic_metrics'];
        yield 'shopware.elasticsearch.create.alias passes through' => ['shopware.elasticsearch.create.alias', 'shopware.elasticsearch.create.alias'];

        // unknown / plugin names collapse to other, bounding label cardinality
        yield 'plugin custom task is other' => ['my_plugin.custom_task', 'other'];
        yield 'empty string is other' => ['', 'other'];
    }
}
