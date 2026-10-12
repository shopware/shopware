<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Event\SystemHeartbeatEvent;
use Shopware\Core\Framework\App\ScheduledTask\SystemHeartbeatHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemHeartbeatHandler::class)]
class SystemCheckTaskHandlerTest extends TestCase
{
    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&Stub $logger;

    private SystemHeartbeatHandler $handler;

    protected function setUp(): void
    {
        $scheduledTaskRepository = static::createStub(EntityRepository::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new SystemHeartbeatHandler(
            $scheduledTaskRepository,
            $this->logger,
            $this->eventDispatcher,
        );
    }

    public function testRunDelegatesToSystemCheckerWithRecurrentContext(): void
    {
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SystemHeartbeatEvent::class));

        $this->handler->run();
    }
}
