<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MailTransportFailedException extends ShopwareHttpException
{
    public function __construct(array $failedRecipients, ?\Throwable $e = null)
    {
        parent::__construct(
            'Failed sending mail to following recipients: {{ recipients }} with Error: {{ errorMessage }}',
            ['recipients' => $failedRecipients, 'recipientsString' => implode(', ', $failedRecipients), 'errorMessage' => $e ? $e->getMessage() : 'Unknown error'],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MAIL_TRANSPORT_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
