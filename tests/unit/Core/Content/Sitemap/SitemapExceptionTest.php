<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\SitemapException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SitemapException::class)]
class SitemapExceptionTest extends TestCase
{
    #[DataProvider('exceptionProvider')]
    public function testExceptions(
        SitemapException $exception,
        int $statusCode,
        string $errorCode,
        string $message
    ): void {
        static::assertSame($statusCode, $exception->getStatusCode());
        static::assertSame($errorCode, $exception->getErrorCode());
        static::assertSame($message, $exception->getMessage());
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public static function exceptionProvider(): iterable
    {
        yield [
            'exceptionFunction' => SitemapException::fileNotReadable('foo'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__FILE_IS_NOT_READABLE',
            'message' => 'File is not readable at foo.',
        ];
    }
}
