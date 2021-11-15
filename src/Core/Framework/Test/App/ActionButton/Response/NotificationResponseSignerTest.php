<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseSigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;

class NotificationResponseSignerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SystemConfigTestBehaviour;

    private ActionButtonResponseSigner $signer;

    private string $secret;

    private ShopIdProvider $shopIdProvider;

    public function setUp(): void
    {
        $this->shopIdProvider = static::createMock(ShopIdProvider::class);
        $this->shopIdProvider->method('getShopId')
            ->willReturn('9XIynsjXGRUN67n5');

        $this->signer = new ActionButtonResponseSigner(
            'http://shop.url',
            '6.4.6.0',
            $this->getContainer()->get(LocaleProvider::class),
            $this->shopIdProvider
        );

        $this->secret = '4da1b5024f22400f9e477c52429b0cb1';
    }

    public function testSignUri(): void
    {
        $signedUri = $this->signer->signUri('http://shopware.com/?foo=bar', $this->secret, Context::createDefaultContext());
        \parse_str($signedUri->getQuery(), $signedQuery);

        static::assertArrayHasKey('shop-id', $signedQuery);
        static::assertEquals('9XIynsjXGRUN67n5', $signedQuery['shop-id']);

        static::assertArrayHasKey('shop-url', $signedQuery);
        static::assertEquals('http://shop.url', $signedQuery['shop-url']);

        static::assertArrayHasKey('timestamp', $signedQuery);

        static::assertArrayHasKey('sw-version', $signedQuery);
        static::assertEquals('6.4.6.0', $signedQuery['sw-version']);

        static::assertArrayHasKey('sw-context-language', $signedQuery);
        static::assertEquals(Defaults::LANGUAGE_SYSTEM, $signedQuery['sw-context-language']);

        static::assertArrayHasKey('sw-user-language', $signedQuery);
        static::assertEquals('en-GB', $signedQuery['sw-user-language']);

        static::assertArrayHasKey('shopware-shop-signature', $signedQuery);
    }
}
