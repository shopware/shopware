<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox;

use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\DeliveryOutcome;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\OutboxEntry;
use Shopware\Core\Framework\Webhook\Service\WebhookSender;

#[Package('framework')]
class DeliveryExecutor
{

    /**
     * @internal
     */
    public function __construct(
        private readonly WebhookSender $webhookSender,
        private readonly RetryCalculator $retryCalculator
    ) {
    }

    public function attempt(OutboxEntry $entry, int $timeout, int $connectTimeout): DeliveryOutcome
    {
        $message = $entry->message;
        $executionCount = $entry->executionCount + 1;
        $startTimestamp = time();

        $requestData = $this->webhookSender->buildRequestOptions($message);

        try {
            $response = $this->webhookSender
                ->withTimeout($timeout)
                ->withConnectTimeout($connectTimeout)
                ->send($message);

            $body = $response->getBody()->getContents();

            return new DeliveryOutcome(
                DeliveryOutcome::STATUS_SUCCESS,
                time() - $startTimestamp,
                $requestData,
                [
                    'headers' => $response->getHeaders(),
                    'body' => $this->decodeBodyIfJson($body),
                ],
                $response->getStatusCode(),
                $response->getReasonPhrase()
            );

        } catch (\Throwable $e) {
            $processingTime = time() - $startTimestamp;
            $responseData = null;
            $statusCode = null;
            $reasonPhrase = null;

            if ($e instanceof RequestException && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();

                $responseData = [
                    'headers' => $response->getHeaders(),
                    'body' => $this->decodeBodyIfJson($body),
                ];
                $statusCode = $response->getStatusCode();
                $reasonPhrase = $response->getReasonPhrase();
            }

            $nextRetry = $this->retryCalculator->calculateNextRetry($executionCount);

            if ($nextRetry) {
                return new DeliveryOutcome(
                    DeliveryOutcome::STATUS_RETRY,
                    $processingTime,
                    $requestData,
                    $responseData,
                    $statusCode,
                    $reasonPhrase,
                    $nextRetry
                );
            }

            return new DeliveryOutcome(
                DeliveryOutcome::STATUS_FAILED,
                $processingTime,
                $requestData,
                $responseData,
                $statusCode,
                $reasonPhrase
            );
        }
    }

    private function decodeBodyIfJson(string $body): mixed
    {
        if ($body === '') {
            return '';
        }

        try {
            return \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $body;
        }
    }
}
