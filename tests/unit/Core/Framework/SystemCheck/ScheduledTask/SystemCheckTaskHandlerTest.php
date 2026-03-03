<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\ScheduledTask\SystemCheckTaskHandler;
use Shopware\Core\Framework\SystemCheck\SystemChecker;

/**
 * @internal
 */
#[CoversClass(SystemCheckTaskHandler::class)]
class SystemCheckTaskHandlerTest extends TestCase
{
    private SystemChecker&MockObject $systemChecker;

    private LoggerInterface&MockObject $logger;

    private SystemCheckTaskHandler $handler;

    protected function setUp(): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->systemChecker = $this->createMock(SystemChecker::class);

        $this->handler = new SystemCheckTaskHandler(
            $scheduledTaskRepository,
            $this->logger,
            $this->systemChecker,
        );
    }

    public function testRunDelegatesToSystemCheckerWithRecurrentContext(): void
    {
        $this->systemChecker
            ->expects($this->once())
            ->method('check')
            ->with(SystemCheckExecutionContext::RECURRENT)
            ->willReturn([]);

        $this->handler->run();
    }

    public function testRunLogsUnhealthyResults(): void
    {
        $unhealthyResult = new Result('DB Check', Status::ERROR, 'Connection failed', false, ['host' => 'localhost']);

        $this->systemChecker
            ->expects($this->once())
            ->method('check')
            ->willReturn([$unhealthyResult]);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'System check "{name}" is unhealthy: {message}',
                [
                    'name' => 'DB Check',
                    'status' => 'ERROR',
                    'message' => 'Connection failed',
                    'extra' => ['host' => 'localhost'],
                ]
            );

        $this->handler->run();
    }

    public function testRunDoesNotLogHealthyResults(): void
    {
        $healthyResult = new Result('Heartbeat', Status::OK, 'ok', true);

        $this->systemChecker
            ->expects($this->once())
            ->method('check')
            ->willReturn([$healthyResult]);

        $this->logger->expects($this->never())->method('error');

        $this->handler->run();
    }

    public function testRunLogsOnlyUnhealthyFromMixedResults(): void
    {
        $healthy = new Result('Heartbeat', Status::OK, 'ok', true);
        $skipped = new Result('SlowCheck', Status::SKIPPED, 'not due', true);
        $unhealthy = new Result('CacheCheck', Status::FAILURE, 'Redis down', false);

        $this->systemChecker
            ->expects($this->once())
            ->method('check')
            ->willReturn([$healthy, $skipped, $unhealthy]);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'System check "{name}" is unhealthy: {message}',
                static::callback(fn (array $context) => $context['name'] === 'CacheCheck')
            );

        $this->handler->run();
    }
}
