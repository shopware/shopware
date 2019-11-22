<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\DeadMessage\RequeueDeadMessagesService;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class RequeueDeadMessagesHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetHandledMessages(): void
    {
        /** @var array $handledMessages */
        $handledMessages = RequeueDeadMessagesHandler::getHandledMessages();
        static::assertCount(1, $handledMessages);
        static::assertEquals(RequeueDeadMessagesTask::class, $handledMessages[0]);
    }

    public function testRun(): void
    {
        $requeueService = $this->createMock(RequeueDeadMessagesService::class);
        $requeueService->expects(static::once())
            ->method('requeue');

        $handler = new RequeueDeadMessagesHandler(
            $this->getContainer()->get('scheduled_task.repository'),
            $requeueService
        );

        $handler->run();
    }
}
