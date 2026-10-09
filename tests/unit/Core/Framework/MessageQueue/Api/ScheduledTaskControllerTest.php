<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Api\ScheduledTaskController;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduledTaskController::class)]
class ScheduledTaskControllerTest extends TestCase
{
    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresPrivilege(string $routeName, array $expectedPrivileges): void
    {
        $route = (new AttributeRouteControllerLoader())->load(ScheduledTaskController::class)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, ScheduledTaskController::class));
        static::assertSame($expectedPrivileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'running due tasks requires queue processing' => ['api.action.scheduled-task.run', ['system:queue:process']];
        yield 'reading the run interval requires scheduled task read' => ['api.action.scheduled-task.min-run-interval', ['scheduled_task:read']];
    }

    public function testRunScheduledTasksQueuesTasks(): void
    {
        $taskScheduler = $this->createMock(TaskScheduler::class);
        $taskScheduler->expects($this->once())->method('queueScheduledTasks');

        $response = (new ScheduledTaskController($taskScheduler))->runScheduledTasks();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"message":"Success"}', $response->getContent());
    }

    public function testGetMinRunIntervalReturnsSchedulerInterval(): void
    {
        $taskScheduler = $this->createMock(TaskScheduler::class);
        $taskScheduler->expects($this->once())->method('getMinRunInterval')->willReturn(300);

        $response = (new ScheduledTaskController($taskScheduler))->getMinRunInterval();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"minRunInterval":300}', $response->getContent());
    }
}
