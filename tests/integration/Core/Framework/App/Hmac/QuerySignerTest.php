<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(QuerySigner::class)]
#[Package('framework')]
class QuerySignerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppEntity $app;

    private QuerySigner $querySigner;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->app = new AppEntity();
        $this->app->setName('TestApp');
        $this->app->setId('app-id');
        $this->app->setAppSecret('lksf#$osck$FSFDSF#$#F43jjidjsfisj-333');
        $this->app->setVersion('1.0.0');

        $this->querySigner = static::getContainer()->get(QuerySigner::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testSignUri(): void
    {
        $signedUri = $this->querySigner->signUri('http://app.url/?foo=bar', $this->app, Context::createDefaultContext());
        parse_str($signedUri->getQuery(), $signedQuery);

        $shopIdConfig = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2);
        static::assertIsArray($shopIdConfig);

        $shopId = ShopId::fromSystemConfig($shopIdConfig);

        static::assertArrayHasKey('shop-id', $signedQuery);
        static::assertSame($shopId->id, $signedQuery['shop-id']);

        static::assertArrayHasKey('shop-url', $signedQuery);
        static::assertSame($shopId->getFingerprint(AppUrl::IDENTIFIER), $signedQuery['shop-url']);

        static::assertArrayHasKey('timestamp', $signedQuery);

        static::assertArrayHasKey('sw-version', $signedQuery);
        static::assertSame(static::getContainer()->getParameter('kernel.shopware_version'), $signedQuery['sw-version']);

        static::assertArrayHasKey('sw-context-language', $signedQuery);
        static::assertSame(Context::createDefaultContext()->getLanguageId(), $signedQuery['sw-context-language']);

        static::assertArrayHasKey('sw-user-language', $signedQuery);
        static::assertSame('en-GB', $signedQuery['sw-user-language']);

        static::assertArrayHasKey('app-version', $signedQuery);
        static::assertSame('1.0.0', $signedQuery['app-version']);

        static::assertNotNull($this->app->getAppSecret());

        static::assertArrayHasKey('shopware-shop-signature', $signedQuery);
        $appSecret = $this->app->getAppSecret();
        static::assertIsString($appSecret);
        static::assertSame(
            \hash_hmac('sha256', Uri::withoutQueryValue($signedUri, 'shopware-shop-signature')->getQuery(), $appSecret),
            $signedQuery['shopware-shop-signature']
        );
    }
}
