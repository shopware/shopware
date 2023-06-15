<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Content\Newsletter\Exception\NewsletterRecipientNotFoundException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class NewsletterException extends HttpException
{
    public const NEWSLETTER_RECIPIENT_NOT_FOUND_CODE = 'CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND';

    public static function recipientNotFound(
        string $identifier,
        string $value
    ): self {
        if (!Feature::isActive('v6.6.0.0')) {
            return new NewsletterRecipientNotFoundException($identifier, $value);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEWSLETTER_RECIPIENT_NOT_FOUND_CODE,
            'The NewsletterRecipient with the identifier "{{ identifier }}" - {{ value }} was not found.',
            ['identifier' => $identifier, 'value' => $value]
        );
    }
}
