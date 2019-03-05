<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MailTransportFailedException extends ShopwareHttpException
{
    protected $code = 'MAIL-TRANSPORT-FAILED';

    public function __construct(array $failedRecipients)
    {
        $message = 'Failed sending mail to following recipients: ' . implode(', ', $failedRecipients);
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
