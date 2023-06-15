<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use NewsletterException::recipientNotFound instead
 */
#[Package('customer-order')]
class NewsletterRecipientNotFoundException extends NewsletterException
{
    public function __construct(
        string $identifier,
        string $value
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use NewsletterException::recipientNotFound instead')
        );

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND',
            'The NewsletterRecipient with the identifier "{{ identifier }}" - {{ value }} was not found.',
            ['identifier' => $identifier, 'value' => $value]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use NewsletterException::recipientNotFound instead')
        );

        return 'CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use NewsletterException::recipientNotFound instead')
        );

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
