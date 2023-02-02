<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ConsumeMessagesControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var AbstractIncrementer
     */
    private $gateway;

    protected function setUp(): void
    {
        $this->gateway = $this->getContainer()->get('shopware.increment.gateway.registry')->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
    }

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

        $gateway = $this->getContainer()->get('shopware.increment.gateway.registry');
        $entries = $gateway->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL)->list('message_queue_stats');

        static::assertArrayHasKey(ProductIndexingMessage::class, $entries);
        static::assertGreaterThan(0, $entries[ProductIndexingMessage::class]['count']);

        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'default']);

        $entries = $this->gateway->list('message_queue_stats');

        static::assertArrayHasKey(ProductIndexingMessage::class, $entries);
        static::assertEquals(0, $entries[ProductIndexingMessage::class]['count']);
    }
}
