<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\ResolvedSeoUrl;

/**
 * @internal
 */
#[CoversClass(ResolvedSeoUrl::class)]
class ResolvedSeoUrlTest extends TestCase
{
    public function testAllFieldsAreExposed(): void
    {
        $resolved = new ResolvedSeoUrl(
            pathInfo: '/detail/1234',
            isCanonical: true,
            id: 'binaryId',
            canonicalPathInfo: '/awesome-product',
            seoPathInfo: 'awesome-product',
        );

        static::assertSame('/detail/1234', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertSame('binaryId', $resolved->id);
        static::assertSame('/awesome-product', $resolved->canonicalPathInfo);
        static::assertSame('awesome-product', $resolved->seoPathInfo);
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $resolved = new ResolvedSeoUrl(pathInfo: '/', isCanonical: false);

        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
        static::assertNull($resolved->id);
        static::assertNull($resolved->canonicalPathInfo);
        static::assertNull($resolved->seoPathInfo);
    }
}
