<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Locale;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Locale\LocaleException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\Locale\LocaleException
 */
class LocaleExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
    public function testItThrowsException(LocaleException $exception, int $statusCode, string $errorCode, string $message): void
    {
        $exceptionWasThrown = false;

        try {
            throw $exception;
        } catch (LocaleException $cmsException) {
            static::assertEquals($statusCode, $cmsException->getStatusCode());
            static::assertEquals($errorCode, $cmsException->getErrorCode());
            static::assertEquals($message, $cmsException->getMessage());

            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Excepted exception with error code ' . $errorCode . ' to be thrown.');
        }
    }

    /**
     * @return array<string, array{exception: LocaleException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield LocaleException::LOCALE_DOES_NOT_EXISTS_EXCEPTION => [
            'exception' => LocaleException::localeDoesNotExists('myCustomLocale'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => LocaleException::LOCALE_DOES_NOT_EXISTS_EXCEPTION,
            'message' => 'The locale myCustomLocale does not exists.',
        ];
    }
}
