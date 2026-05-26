<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Hmac;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(QuerySigner::class)]
class QuerySignerTest extends TestCase
{
    public function testSignUri(): void
    {
        $inAppPurchase = StaticInAppPurchaseFactory::createWithFeatures(['extension-1' => ['purchase-1', 'purchase-2'], 'extension-2' => ['purchase-3']]);

        $userId = Uuid::randomHex();

        $context = new Context(new AdminApiSource($userId));

        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider
            ->expects($this->once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('en-GB');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn(ShopId::v2('shopId'));

        $app = new AppEntity();
        $app->setName('extension-1');
        $app->setAppSecret('devSecret');
        $app->setId(Uuid::randomHex());
        $app->setVersion('1.0.0');

        $querySigner = new QuerySigner('http://shop.url', '1.0.0', $localeProvider, $shopIdProvider, $inAppPurchase);
        $signedQuery = $querySigner->signUri('http://app.url/?foo=bar', $app, $context);

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('shop-id', $url);
        static::assertArrayHasKey('shop-url', $url);
        static::assertArrayHasKey('timestamp', $url);
        static::assertArrayHasKey('sw-version', $url);
        static::assertArrayHasKey('in-app-purchases', $url);
        static::assertArrayHasKey('sw-context-language', $url);
        static::assertArrayHasKey('sw-user-language', $url);
        static::assertArrayHasKey('shopware-shop-signature', $url);
        static::assertArrayHasKey('app-version', $url);
        static::assertArrayHasKey('sw-user-id', $url);

        static::assertSame('shopId', $url['shop-id']);
        static::assertSame('http://shop.url', $url['shop-url']);
        static::assertIsNumeric($url['timestamp']);
        static::assertSame('1.0.0', $url['sw-version']);
        static::assertSame('a6a4063ffda65516983ad40e8dc91db6', $url['in-app-purchases']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $url['sw-context-language']);
        static::assertSame('en-GB', $url['sw-user-language']);
        static::assertSame('1.0.0', $url['app-version']);
        static::assertSame($userId, $url['sw-user-id']);
    }

    #[DataProvider('userIdDataProvider')]
    public function testUserIdInSignedUri(Context $context, string $expectedUserId): void
    {
        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider
            ->expects($this->once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('en-GB');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn(ShopId::v2('shopId'));

        $app = new AppEntity();
        $app->setName('extension-1');
        $app->setAppSecret('devSecret');
        $app->setId(Uuid::randomHex());
        $app->setVersion('1.0.0');

        $querySigner = new QuerySigner(
            'http://shop.url',
            '1.0.0',
            $localeProvider,
            $shopIdProvider,
            StaticInAppPurchaseFactory::createWithFeatures(),
        );

        $signedQuery = $querySigner->signUri(
            'http://app.url/?foo=bar',
            $app,
            $context
        );

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('sw-user-id', $url);
        static::assertSame($expectedUserId, $url['sw-user-id']);
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
            $this->createMock(ShopIdProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
        );

        $this->expectExceptionObject(AppException::appSecretMissing('Foo'));

        $querySigner->signUri('http://app.url/?foo=bar', $app, Context::createDefaultContext());
    }

    /**
     * @return \Generator<string, array{Context, string}>
     */
    public static function userIdDataProvider(): \Generator
    {
        $userId = Uuid::randomHex();

        yield 'admin api source with user' => [
            new Context(new AdminApiSource($userId)),
            $userId,
        ];

        yield 'admin api source without user' => [
            new Context(new AdminApiSource(null)),
            '',
        ];

        yield 'non-admin api source' => [
            Context::createDefaultContext(),
            '',
        ];
    }
}
