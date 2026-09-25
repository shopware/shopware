<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Elasticsearch\Admin\AdminCreateAliasTaskHandler;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AdminCreateAliasTaskHandler::class)]
class AdminCreateAliasTaskHandlerTest extends TestCase
{
    public function testSwapsFinishedAliases(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('swapFinishedAliases');

        $this->createHandler($registry, true)->run();
    }

    public function testDoesNothingWhenAdminElasticsearchIsDisabled(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->never())->method('swapFinishedAliases');

        $this->createHandler($registry, false)->run();
    }

    public function testLetsTheHelperDecideWhetherAFailureIsThrown(): void
    {
        $exception = new \RuntimeException('cluster unreachable');

        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('swapFinishedAliases')->willThrowException($exception);

        // the helper rethrows in the test environment, which is how the task surfaces a broken cluster
        $this->expectExceptionObject($exception);

        $this->createHandler($registry, true)->run();
    }

    private function createHandler(AdminSearchRegistry $registry, bool $enabled): AdminCreateAliasTaskHandler
    {
        return new AdminCreateAliasTaskHandler(
            StaticEntityRepository::of(ScheduledTaskCollection::class, []),
            new NullLogger(),
            $registry,
            new AdminElasticsearchHelper($enabled, false, 'sw-admin', 'test', true, new NullLogger())
        );
    }
}
