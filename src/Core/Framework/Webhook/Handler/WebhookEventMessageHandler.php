<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Handler;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlVersion;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('framework')]
final class WebhookEventMessageHandler
{
    private const TIMEOUT = 20;
    private const CONNECT_TIMEOUT = 10;
    private const MAX_REDIRECTS = 5;

    /**
     * @var \Closure(): bool
     */
    private readonly \Closure $canUseCurl;

    /**
     * @internal
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly EntityRepository $webhookEventLogRepository,
        private readonly RelatedWebhooks $relatedWebhooks,
        private readonly WebhookSigningSecretResolver $signingSecretResolver,
        private readonly WebhookTargetValidator $targetValidator,
        ?\Closure $canUseCurl = null,
    ) {
        $this->canUseCurl = $canUseCurl ?? static fn (): bool => \defined('CURLOPT_CUSTOMREQUEST') && \function_exists('curl_exec') && CurlVersion::supportsCurlHandler();
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        $shopwareVersion = $message->getShopwareVersion();

        $payload = $message->getPayload();
        $url = $message->getUrl();

        $timestamp = time();
        $payload['timestamp'] = $timestamp;

        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'sw-version' => $shopwareVersion,
            ],
            $message->getWebhookHeaders()
        );

        // LanguageId and UserLocale will be required from 6.5.0 onward
        if ($message->getLanguageId() && $message->getUserLocale()) {
            $headers = array_merge($headers, [AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $message->getLanguageId(), AuthMiddleware::SHOPWARE_USER_LANGUAGE => $message->getUserLocale()]);
        }

        $requestContent = [
            'headers' => $headers,
            'body' => $jsonPayload,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::TIMEOUT,
        ];

        // Resolve the signing secret at delivery time so a webhook queued or retried across an
        // app-secret rotation is signed with the secret the app currently verifies against.
        $secret = $this->signingSecretResolver->resolve($message);
        if ($secret) {
            $requestContent[AuthMiddleware::APP_REQUEST_TYPE] = [
                AuthMiddleware::APP_SECRET => $secret,
            ];
        }

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
            $response = $this->sendWithRedirects($url, $requestContent);

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

            try {
                $this->relatedWebhooks->updateRelated($message->getWebhookId(), ['error_count' => 0], $context);
            } catch (AppNotFoundException|WriteTypeIntendException $e) {
                // may happen if app or webhook got deleted in the meantime,
                // we don't need to update the error-count in that case, so we can ignore the error
            }
        } catch (\Throwable $e) {
            $payload = [
                'id' => $message->getWebhookEventId(),
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED, // we use the message retry mechanism to retry the message here so we set the status to queued, because it will be automatically executed again.
                'processingTime' => time() - $timestamp,
            ];

            if ($e instanceof RequestException && $e->getResponse() !== null) {
                $response = $e->getResponse();
                $body = $response->getBody()->getContents();
                if (json_validate($body)) {
                    $body = \json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
                }
                $payload = array_merge($payload, [
                    'responseContent' => [
                        'headers' => $response->getHeaders(),
                        'body' => $body,
                    ],
                    'responseStatusCode' => $response->getStatusCode(),
                    'responseReasonPhrase' => $response->getReasonPhrase(),
                ]);
            }

            $this->webhookEventLogRepository->update([$payload], $context);

            if ($e instanceof WebhookException && $e->getErrorCode() === WebhookException::CURL_NOT_AVAILABLE) {
                throw $e;
            }

            if ($e instanceof BadResponseException && $message->getAppId()) {
                throw WebhookException::appWebhookFailedException($message->getWebhookId(), $message->getAppId(), $e);
            }

            throw WebhookException::webhookFailedException($message->getWebhookId(), $e);
        }
    }

    /**
     * @param array<string, mixed> $requestContent
     */
    private function sendWithRedirects(string $url, array $requestContent, int $redirects = 0, string $method = 'POST'): ResponseInterface
    {
        $target = $this->targetValidator->validate($url);
        if ($target === null) {
            throw $redirects > 0 ? WebhookException::redirectTargetNotAllowed() : WebhookException::targetNotAllowed();
        }

        if (!$this->canUseCurl()) {
            throw WebhookException::curlNotAvailable();
        }

        // Guzzle redirects are disabled so every redirect target can be validated and pinned before following it.
        $requestContent['allow_redirects'] = false;
        $curlOptions = $requestContent['curl'] ?? [];
        if (!\is_array($curlOptions)) {
            $curlOptions = [];
        }

        $resolvePins = $curlOptions[\CURLOPT_RESOLVE] ?? [];
        if (!\is_array($resolvePins)) {
            $resolvePins = [];
        }

        $resolvePins[] = \sprintf('%s:%d:%s', $target->host, $target->port, $this->formatCurlResolveAddress($target->ip));
        $curlOptions[\CURLOPT_RESOLVE] = $resolvePins;
        $requestContent['curl'] = $curlOptions;

        $response = $this->client->request($method, $url, $requestContent);
        if (!\in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true)) {
            return $response;
        }

        if ($redirects >= self::MAX_REDIRECTS) {
            throw WebhookException::maximumRedirectsExceeded();
        }

        $location = $response->getHeaderLine('Location');
        if ($location === '') {
            return $response;
        }

        $redirectUrl = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        if (\in_array($response->getStatusCode(), [301, 302, 303], true)) {
            unset($requestContent['body'], $requestContent['headers']['Content-Length'], $requestContent['headers']['Transfer-Encoding']);
            $method = 'GET';
        }

        if (UriComparator::isCrossOrigin(new Uri($url), new Uri($redirectUrl))) {
            $requestContent['headers'] = array_filter(
                $requestContent['headers'],
                static fn (string $name): bool => !\in_array(strtolower($name), ['authorization', 'cookie'], true),
                \ARRAY_FILTER_USE_KEY
            );
        }

        return $this->sendWithRedirects($redirectUrl, $requestContent, $redirects + 1, $method);
    }

    private function formatCurlResolveAddress(string $ip): string
    {
        return str_contains($ip, ':') ? \sprintf('[%s]', $ip) : $ip;
    }

    private function canUseCurl(): bool
    {
        return ($this->canUseCurl)();
    }
}
