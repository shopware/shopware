<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class WebhookException extends HttpException
{
    public const WEBHOOK_FAILED = 'FRAMEWORK__WEBHOOK_FAILED';
    public const APP_WEBHOOK_FAILED = 'FRAMEWORK__APP_WEBHOOK_FAILED';
    public const INVALID_DATA_MAPPING = 'FRAMEWORK__WEBHOOK_INVALID_DATA_MAPPING';
    public const UNKNOWN_DATA_TYPE = 'FRAMEWORK__WEBHOOK_UNKNOWN_DATA_TYPE';
    public const TARGET_NOT_ALLOWED = 'FRAMEWORK__WEBHOOK_TARGET_NOT_ALLOWED';
    public const REDIRECT_TARGET_NOT_ALLOWED = 'FRAMEWORK__WEBHOOK_REDIRECT_TARGET_NOT_ALLOWED';
    public const MAXIMUM_REDIRECTS_EXCEEDED = 'FRAMEWORK__WEBHOOK_MAXIMUM_REDIRECTS_EXCEEDED';
    public const CURL_NOT_AVAILABLE = 'FRAMEWORK__WEBHOOK_CURL_NOT_AVAILABLE';

    public static function webhookFailedException(string $webhookId, \Throwable $e): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::WEBHOOK_FAILED,
            'Webhook "{{ webhookId }}" failed with error: {{ error }}.',
            ['webhookId' => $webhookId, 'error' => $e->getMessage()],
            $e
        );
    }

    public static function appWebhookFailedException(string $webhookId, string $appId, \Throwable $e): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_WEBHOOK_FAILED,
            'Webhook "{{ webhookId }}" from "{{ appId }}" failed with error: {{ error }}.',
            ['webhookId' => $webhookId, 'appId' => $appId, 'error' => $e->getMessage()],
            $e
        );
    }

    public static function targetNotAllowed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TARGET_NOT_ALLOWED,
            'Webhook target is not allowed.'
        );
    }

    public static function redirectTargetNotAllowed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REDIRECT_TARGET_NOT_ALLOWED,
            'Redirect target is not allowed.'
        );
    }

    public static function maximumRedirectsExceeded(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAXIMUM_REDIRECTS_EXCEEDED,
            'Maximum redirects exceeded.'
        );
    }

    public static function curlNotAvailable(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CURL_NOT_AVAILABLE,
            'Webhook delivery requires cURL support.'
        );
    }

    public static function invalidDataMapping(string $propertyName, string $className): \RuntimeException
    {
        return new \RuntimeException(
            \sprintf(
                'Invalid available DataMapping, could not get property "%s" on instance of %s',
                $propertyName,
                $className
            )
        );
    }

    public static function unknownEventDataType(string $type): \RuntimeException
    {
        return new \RuntimeException('Unknown EventDataType: ' . $type);
    }
}
