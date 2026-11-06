<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\CleanupIndicesTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupIndicesTask::class)]
class CleanupIndicesTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('shopware.elasticsearch.cleanup.indices', CleanupIndicesTask::getTaskName());
        // ScheduledTask::DAILY is protected; assert the resolved value (one day in seconds).
        static::assertSame(86400, CleanupIndicesTask::getDefaultInterval());
        static::assertTrue(CleanupIndicesTask::shouldRescheduleOnFailure());
    }

    public function testRunsWhenEitherElasticsearchIsEnabled(): void
    {
        static::assertTrue(CleanupIndicesTask::shouldRun($this->bag(true, false)));
        static::assertTrue(CleanupIndicesTask::shouldRun($this->bag(false, true)));
        static::assertTrue(CleanupIndicesTask::shouldRun($this->bag(true, true)));
    }

    public function testDoesNotRunWhenBothAreDisabled(): void
    {
        static::assertFalse(CleanupIndicesTask::shouldRun($this->bag(false, false)));
    }

    private function bag(bool $elasticsearch, bool $administration): ParameterBag
    {
        return new ParameterBag([
            'elasticsearch.enabled' => $elasticsearch,
            'elasticsearch.administration.enabled' => $administration,
        ]);
    }
}
