<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class WebhookException extends HttpException
{
    public const WEBHOOK_FAILED = 'FRAMEWORK__WEBHOOK_FAILED';
    public const APP_WEBHOOK_FAILED = 'FRAMEWORK__APP_WEBHOOK_FAILED';

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
}
