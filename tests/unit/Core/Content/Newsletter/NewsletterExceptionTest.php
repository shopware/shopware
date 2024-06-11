<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(NewsletterException::class)]
class NewsletterExceptionTest extends TestCase
{
    public function testRecipientNotFoundn(): void
    {
        $exception = NewsletterException::recipientNotFound('id-1', 'value-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(NewsletterException::NEWSLETTER_RECIPIENT_NOT_FOUND_CODE, $exception->getErrorCode());
        static::assertSame('The NewsletterRecipient with the identifier "id-1" - value-1 was not found.', $exception->getMessage());
        static::assertSame(['identifier' => 'id-1', 'value' => 'value-1'], $exception->getParameters());
    }

    public function testNewsletterThrottled(): void
    {
        $exception = NewsletterException::newsletterThrottled(2);

        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
        static::assertSame(NewsletterException::NEWSLETTER_RECIPIENT_THROTTLED, $exception->getErrorCode());
        static::assertSame('Too many requests, try again in 2 seconds.', $exception->getMessage());
        static::assertSame(['seconds' => 2], $exception->getParameters());
    }

    public function testMissingEmailParameter(): void
    {
        $exception = NewsletterException::missingEmailParameter();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(NewsletterException::MISSING_EMAIL_PARAMETER, $exception->getErrorCode());
        static::assertSame('The email parameter is missing.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }
}
