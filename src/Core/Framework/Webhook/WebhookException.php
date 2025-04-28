<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\Feature;
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

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function invalidDataMapping(string $propertyName, string $className): self|\RuntimeException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new \RuntimeException(
                \sprintf(
                    'Invalid available DataMapping, could not get property "%s" on instance of %s',
                    $propertyName,
                    $className
                )
            );
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_DATA_MAPPING,
            'Invalid available DataMapping, could not get property "{{ propertyName }}" on instance of {{ class }}',
            ['propertyName' => $propertyName, 'class' => $className]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function unknownEventDataType(string $type): self|\RuntimeException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new \RuntimeException('Unknown EventDataType: ' . $type);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_DATA_TYPE,
            'Unknown EventDataType: {{ type }}',
            ['type' => $type]
        );
    }
}
