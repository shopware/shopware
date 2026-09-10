<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Admin\AdminCreateAliasTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AdminCreateAliasTask::class)]
class AdminCreateAliasTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('shopware.elasticsearch.admin.create.alias', AdminCreateAliasTask::getTaskName());
        // ScheduledTask::MINUTELY is protected; assert the resolved value (five minutes in seconds).
        static::assertSame(300, AdminCreateAliasTask::getDefaultInterval());
        static::assertTrue(AdminCreateAliasTask::shouldRescheduleOnFailure());
    }

    public function testRunsOnlyWhenAdminElasticsearchIsEnabled(): void
    {
        static::assertTrue(AdminCreateAliasTask::shouldRun(new ParameterBag(['elasticsearch.administration.enabled' => true])));
        static::assertFalse(AdminCreateAliasTask::shouldRun(new ParameterBag(['elasticsearch.administration.enabled' => false])));
    }
}
