<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

class WebhookEventMessageHandler extends AbstractMessageHandler
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;

    private Client $client;

    private EntityRepositoryInterface $webhookRepository;

    private EntityRepositoryInterface $webhookEventLogRepository;

    public function __construct(Client $client, EntityRepositoryInterface $webhookRepository, EntityRepositoryInterface $webhookEventLogRepository)
    {
        $this->client = $client;
        $this->webhookRepository = $webhookRepository;
        $this->webhookEventLogRepository = $webhookEventLogRepository;
    }

    /**
     * @param WebhookEventMessage $message
     */
    public function handle($message): void
    {
        $shopwareVersion = $message->getShopwareVersion();
        $payload = $message->getPayload();
        $url = $message->getUrl();

        $timestamp = time();
        $payload['timestamp'] = $timestamp;

        $header = [
            'Content-Type' => 'application/json',
            'sw-version' => $shopwareVersion,
        ];

        /** @var string $jsonPayload */
        $jsonPayload = json_encode($payload);

        if ($message->getSecret()) {
            $header['shopware-shop-signature'] = hash_hmac('sha256', $jsonPayload, $message->getSecret());
        }

        $requestContent = [
            'headers' => $header,
            'body' => $jsonPayload,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
        ];

        $context = Context::createDefaultContext();

        $this->webhookEventLogRepository->update([
            [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_RUNNING,
                'timestamp' => $timestamp,
                'requestContent' => $requestContent,
            ],
        ], $context);

        try {
            $response = $this->client->post($url, $requestContent);

            $this->webhookEventLogRepository->update([
                [
                    'id' => $message->getWebhookEventId(),
                    'deliveryStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'processingTime' => time() - $timestamp,
                    'responseContent' => [
                        'headers' => $response->getHeaders(),
                        'body' => \json_decode($response->getBody()->getContents(), true),
                    ],
                    'responseStatusCode' => $response->getStatusCode(),
                    'responseReasonPhrase' => $response->getReasonPhrase(),
                ],
            ], $context);

            $this->webhookRepository->update([
                [
                    'id' => $message->getWebhookId(),
                    'errorCount' => 0,
                ],
            ], $context);
        } catch (\Throwable $e) {
            $payload = [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED, // we use the message retry mechanism to retry the message here so we set the status to queued, because it will be automatically executed again.
                'processingTime' => time() - $timestamp,
            ];

            if ($e instanceof RequestException && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $payload = array_merge($payload, [
                    'responseContent' => [
                        'headers' => $response->getHeaders(),
                        'body' => \json_decode($response->getBody()->getContents(), true),
                    ],
                    'responseStatusCode' => $response->getStatusCode(),
                    'responseReasonPhrase' => $response->getReasonPhrase(),
                ]);
            }

            $this->webhookEventLogRepository->update([$payload], $context);

            throw new MessageFailedException($message, static::class, $e);
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [WebhookEventMessage::class];
    }
}
