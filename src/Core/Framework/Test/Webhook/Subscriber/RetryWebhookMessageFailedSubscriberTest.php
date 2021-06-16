<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Subscriber;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Event\RetryWebhookMessageFailedEvent;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RetryWebhookMessageFailedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GuzzleTestClientBehaviour;

    private Context $context;

    private EventDispatcherInterface $eventDispatcher;

    public function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
    }

    public function testHandleWebhookMessageFailed(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();
        $deadMessageId = Uuid::randomHex();

        $deadMessageRepository = $this->getContainer()->get('dead_message.repository');
        $appRepository = $this->getContainer()->get('app.repository');
        $webhookEventLogRepository = $this->getContainer()->get('webhook_event_log.repository');

        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'eventName' => 'order',
                    'url' => 'https://test.com',
                ],
            ],
        ]], $this->context);

        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], $appId, $webhookId, '6.4', 'https://test.com');
        $envelope = new Envelope($webhookEventMessage);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $response = new Response(404);
        $request = new Request('POST', 'http://test.com');

        $exception = new ClientException('Not Found', $request, $response);

        $deadMessageRepository->create([[
            'id' => $deadMessageId,
            'originalMessageClass' => \get_class($envelope->getMessage()),
            'serializedOriginalMessage' => serialize($envelope->getMessage()),
            'handlerClass' => WebhookEventMessageHandler::class,
            'encrypted' => false,
            'nextExecutionTime' => DeadMessageEntity::calculateNextExecutionTime(1),
            'exception' => \get_class($exception),
            'exceptionMessage' => $exception->getMessage(),
            'exceptionFile' => $exception->getFile(),
            'exceptionLine' => $exception->getLine(),
            'errorCount' => 3,
        ]], $this->context);

        $deadMessage = $deadMessageRepository->search(new Criteria([$deadMessageId]), $this->context)->get($deadMessageId);

        $this->eventDispatcher->dispatch(new RetryWebhookMessageFailedEvent($deadMessage, $this->context));

        $deadMessages = $deadMessageRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(0, $deadMessages);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)->first();
        static::assertEquals($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_FAILED);
    }
}
