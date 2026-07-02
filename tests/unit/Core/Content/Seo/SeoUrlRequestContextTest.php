<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlRequestContext;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(SeoUrlRequestContext::class)]
class SeoUrlRequestContextTest extends TestCase
{
    public function testReadonlyConstruction(): void
    {
        $languageId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $context = new SeoUrlRequestContext(
            languageId: $languageId,
            salesChannelId: $salesChannelId,
            pathInfo: 'awesome-product',
            queryString: 'test=123',
        );

        static::assertSame($languageId, $context->languageId);
        static::assertSame($salesChannelId, $context->salesChannelId);
        static::assertSame('awesome-product', $context->pathInfo);
        static::assertSame('test=123', $context->queryString);
    }

    public function testQueryStringIsOptional(): void
    {
        $context = new SeoUrlRequestContext(
            languageId: Uuid::randomHex(),
            salesChannelId: Uuid::randomHex(),
            pathInfo: 'awesome-product',
        );

        static::assertNull($context->queryString);
    }
}
