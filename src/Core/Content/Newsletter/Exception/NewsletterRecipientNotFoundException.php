<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Exception;

use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - Will be removed, use NewsletterException::recipientNotFound instead
 */
#[Package('buyers-experience')]
class NewsletterRecipientNotFoundException extends NewsletterException
{
    public function __construct(
        string $identifier,
        string $value
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND',
            'The NewsletterRecipient with the identifier "{{ identifier }}" - {{ value }} was not found.',
            ['identifier' => $identifier, 'value' => $value]
        );
    }
}
