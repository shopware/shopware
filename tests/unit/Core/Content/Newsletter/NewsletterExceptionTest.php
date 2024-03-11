<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(NewsletterException::class)]
class NewsletterExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
    public function testItThrowsException(ShopwareHttpException|NewsletterException $exception, int $statusCode, string $errorCode, string $message): void
    {
        try {
            throw $exception;
        } catch (ShopwareHttpException|NewsletterException $newsletterException) {
            $caughtException = $newsletterException;
        }

        static::assertEquals($statusCode, $caughtException->getStatusCode());
        static::assertEquals($errorCode, $caughtException->getErrorCode());
        static::assertEquals($message, $caughtException->getMessage());
    }

    /**
     * @return array<string, array{exception: ShopwareHttpException|NewsletterException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield NewsletterException::NEWSLETTER_RECIPIENT_NOT_FOUND_CODE => [
            'exception' => NewsletterException::recipientNotFound('id-1', 'value-1'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => NewsletterException::NEWSLETTER_RECIPIENT_NOT_FOUND_CODE,
            'message' => 'The NewsletterRecipient with the identifier "id-1" - value-1 was not found.',
        ];

        yield NewsletterException::NEWSLETTER_RECIPIENT_THROTTLED => [
            'exception' => NewsletterException::newsletterThrottled(2),
            'statusCode' => Response::HTTP_TOO_MANY_REQUESTS,
            'errorCode' => NewsletterException::NEWSLETTER_RECIPIENT_THROTTLED,
            'message' => 'Too many requests, try again in 2 seconds.',
        ];

        yield NewsletterException::MISSING_EMAIL_PARAMETER => [
            'exception' => NewsletterException::missingEmailParameter(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => NewsletterException::MISSING_EMAIL_PARAMETER,
            'message' => 'The email parameter is missing.',
        ];
    }
}
