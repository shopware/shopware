<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class MailEventConfigurationException extends ShopwareHttpException
{
    public function __construct(
        string $message,
        string $eventClass
    ) {
        parent::__construct(
            'Failed processing the mail event: {{ errorMessage }}. {{ eventClass }}',
            [
                'errorMessage' => $message,
                'eventClass' => $eventClass,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MAIL_INVALID_EVENT_CONFIGURATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
