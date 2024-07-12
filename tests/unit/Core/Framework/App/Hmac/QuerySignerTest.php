<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Hmac;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(QuerySigner::class)]
class QuerySignerTest extends TestCase
{
    public function testSignUri(): void
    {
        $context = new Context(new AdminApiSource(null));

        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider
            ->expects(static::once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('en-GB');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects(static::once())
            ->method('getShopId')
            ->willReturn('shopId');

        $app = new AppEntity();
        $app->setAppSecret('devSecret');
        $app->setId('extension-1');

        $querySigner = new QuerySigner('http://shop.url', '1.0.0', $localeProvider, $shopIdProvider);
        $signedQuery = $querySigner->signUri('http://app.url/?foo=bar', $app, $context);

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('shop-id', $url);
        static::assertArrayHasKey('shop-url', $url);
        static::assertArrayHasKey('timestamp', $url);
        static::assertArrayHasKey('sw-version', $url);
        static::assertArrayHasKey('sw-context-language', $url);
        static::assertArrayHasKey('sw-user-language', $url);
        static::assertArrayHasKey('shopware-shop-signature', $url);

        static::assertSame('shopId', $url['shop-id']);
        static::assertSame('http://shop.url', $url['shop-url']);
        static::assertIsNumeric($url['timestamp']);
        static::assertSame('1.0.0', $url['sw-version']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $url['sw-context-language']);
        static::assertSame('en-GB', $url['sw-user-language']);
    }

    public function testThrowsWithoutAppSecret(): void
    {
        $app = new AppEntity();
        $app->setName('Foo');
        $app->setAppSecret(null);

        $querySigner = new QuerySigner(
            'http://shop.url',
            '1.0.0',
            $this->createMock(LocaleProvider::class),
            $this->createMock(ShopIdProvider::class)
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App secret is missing for app Foo');

        $querySigner->signUri('http://app.url/?foo=bar', $app, Context::createDefaultContext());
    }
}
