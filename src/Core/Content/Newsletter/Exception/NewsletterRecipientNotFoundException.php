<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class NewsletterRecipientNotFoundException extends ShopwareHttpException
{
    public function __construct(
        string $identifier,
        string $value
    ) {
        parent::__construct(
            'The NewsletterRecipient with the identifier "{{ identifier }}" - {{ value }} was not found.',
            ['identifier' => $identifier, 'value' => $value]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
