<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\SitemapException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapException::class)]
class SitemapExceptionTest extends TestCase
{
    public function testFileNotReadable(): void
    {
        $exception = SitemapException::fileNotReadable('/path/to/file');
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('CONTENT__FILE_IS_NOT_READABLE', $exception->getErrorCode());
        static::assertSame('File is not readable at /path/to/file.', $exception->getMessage());
    }

    public function testSitemapAlreadyLocked(): void
    {
        $exception = SitemapException::sitemapAlreadyLocked(Generator::generateSalesChannelContext());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CONTENT__SITEMAP_ALREADY_LOCKED', $exception->getErrorCode());
        static::assertSame('Cannot acquire lock for sales channel 98432def39fc4624b33213a56b8c944d and language 2fbb5fe2e29a4d70aa5854ce7ce3e20b', $exception->getMessage());
    }

    public function testInvalidDomain(): void
    {
        $exception = SitemapException::invalidDomain();
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('CONTENT__INVALID_DOMAIN', $exception->getErrorCode());
        static::assertSame('Invalid domain', $exception->getMessage());
    }
}
