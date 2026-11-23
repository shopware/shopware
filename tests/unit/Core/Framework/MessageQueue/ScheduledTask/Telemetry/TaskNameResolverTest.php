<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\TaskNameResolver;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

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
        static::assertSame($expected, $this->createResolver()->resolve($taskName));
    }

    public static function taskNameProvider(): \Generator
    {
        // core task names pass through unchanged (code-level rule, no configured entry needed)
        yield 'version.cleanup passes through' => ['version.cleanup', 'version.cleanup'];
        yield 'shopware.invalidate_cache passes through' => ['shopware.invalidate_cache', 'shopware.invalidate_cache'];
        yield 'theme.delete_files passes through' => ['theme.delete_files', 'theme.delete_files'];
        yield 'telemetry.collect_periodic_metrics passes through' => ['telemetry.collect_periodic_metrics', 'telemetry.collect_periodic_metrics'];
        yield 'shopware.elasticsearch.create.alias passes through' => ['shopware.elasticsearch.create.alias', 'shopware.elasticsearch.create.alias'];

        // unknown / plugin names collapse to other, bounding label cardinality
        yield 'plugin custom task is other' => ['my_plugin.custom_task', 'other'];
        yield 'empty string is other' => ['', 'other'];
    }

    public function testPluginOptsInWithIdentityMappingEntry(): void
    {
        $resolver = $this->createResolver(['swag_blog.cleanup' => 'swag_blog.cleanup']);

        static::assertSame('swag_blog.cleanup', $resolver->resolve('swag_blog.cleanup'));
    }

    public function testConfiguredEntryFoldsCoreTask(): void
    {
        // configured beats code-level: an operator can fold even a core task name
        $resolver = $this->createResolver(['telemetry.collect_periodic_metrics' => 'other']);

        static::assertSame('other', $resolver->resolve('telemetry.collect_periodic_metrics'));
    }

    /**
     * @param array<string, string> $entries
     */
    private function createResolver(array $entries = []): TaskNameResolver
    {
        // core ships an empty table: the core task names are the resolver's code-level pass-through rule
        return new TaskNameResolver(new LabelMappingRegistry([TaskNameResolver::MAPPING => $entries], 'other'));
    }
}
