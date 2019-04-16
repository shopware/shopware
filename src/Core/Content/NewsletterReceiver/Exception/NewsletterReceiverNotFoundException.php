<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class NewsletterReceiverNotFoundException extends ShopwareHttpException
{
    public function __construct(string $identifier, string $value)
    {
        parent::__construct(
            'The NewsletterReceiver with the identifier "{{ identifier }}" - {{ value }} was not found.',
            ['identifier' => $identifier, 'value' => $value]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__NEWSLETTER_RECEIVER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
