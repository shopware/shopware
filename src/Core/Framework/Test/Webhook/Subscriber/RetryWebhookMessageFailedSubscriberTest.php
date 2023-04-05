<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Subscriber;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @internal
 */
class RetryWebhookMessageFailedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GuzzleTestClientBehaviour;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testHandleWebhookMessageFailed(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

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

        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], $appId, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB');

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

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        $this->getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)->first();
        static::assertEquals($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_FAILED);
    }
}
