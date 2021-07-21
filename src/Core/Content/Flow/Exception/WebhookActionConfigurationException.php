<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class WebhookActionConfigurationException extends ShopwareHttpException
{
    public function __construct(string $message, string $eventClass)
    {
        parent::__construct(
            'Failed processing the webhook action: {{ errorMessage }}. {{ eventClass }}',
            [
                'errorMessage' => $message,
                'eventClass' => $eventClass,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__WEBHOOK_INVALID_EVENT_CONFIGURATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
