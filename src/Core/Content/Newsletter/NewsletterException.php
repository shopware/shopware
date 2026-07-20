<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiterException;
use Symfony\Component\HttpFoundation\Response;

#[Package('after-sales')]
class NewsletterException extends HttpException
{
    public const NEWSLETTER_RECIPIENT_NOT_FOUND_CODE = 'CONTENT__NEWSLETTER_RECIPIENT_NOT_FOUND';
    public const NEWSLETTER_RECIPIENT_THROTTLED = 'CONTENT__NEWSLETTER_RECIPIENT_THROTTLED';

    public const MISSING_EMAIL_PARAMETER = 'CONTENT__MISSING_EMAIL_PARAMETER';

    public static function missingEmailParameter(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_EMAIL_PARAMETER,
            'The email parameter is missing.'
        );
    }

    public static function recipientNotFound(
        string $identifier,
        string $value
    ): self {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEWSLETTER_RECIPIENT_NOT_FOUND_CODE,
            'The NewsletterRecipient with the identifier "{{ identifier }}" - {{ value }} was not found.',
            ['identifier' => $identifier, 'value' => $value]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use RateLimiterException::limitExceeded
     */
    public static function newsletterThrottled(int $waitTime): NewsletterException
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(
                self::class,
                __FUNCTION__,
                'v6.8.0.0',
                'RateLimiterException::limitExceeded()'
            )
        );

        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::NEWSLETTER_RECIPIENT_THROTTLED,
            'Too many requests, try again in {{ seconds }} seconds.',
            ['seconds' => $waitTime],
        );
    }
}
