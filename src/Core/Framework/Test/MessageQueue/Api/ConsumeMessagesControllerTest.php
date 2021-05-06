<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\MessageQueueStatsEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ConsumeMessagesControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    public function testConsumeMessages(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM scheduled_task');

        // queue a task
        $repo = $this->getContainer()->get('scheduled_task.repository');
        $taskId = Uuid::randomHex();
        $repo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('-1 second'),
            ],
        ], Context::createDefaultContext());

        $url = '/api/_action/scheduled-task/run';
        $client = $this->getBrowser();
        $client->request('POST', $url);

        // consume the queued task
        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'default']);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('handledMessages', $response);
        static::assertIsInt($response['handledMessages']);
        static::assertEquals(1, $response['handledMessages']);
    }

    public function testMessageStatsDecrement(): void
    {
        $messageBus = $this->getContainer()->get('messenger.bus.shopware');
        $message = new ProductIndexingMessage([Uuid::randomHex()]);
        $messageBus->dispatch($message);

        /** @var EntityRepositoryInterface $queueRepo */
        $queueRepo = $this->getContainer()->get('message_queue_stats.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->setLimit(1)->addFilter(new EqualsFilter('name', ProductIndexingMessage::class));

        /** @var MessageQueueStatsEntity $queueStatus */
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertGreaterThan(0, $queueStatus->getSize());

        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'default']);

        /** @var MessageQueueStatsEntity $queueStatus */
        $queueStatus = $queueRepo->search($criteria, $context)->first();

        static::assertEquals(0, $queueStatus->getSize());
    }
}
