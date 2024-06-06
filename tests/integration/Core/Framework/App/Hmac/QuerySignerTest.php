<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(QuerySigner::class)]
#[Package('core')]
class QuerySignerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppEntity $app;

    private QuerySigner $querySigner;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->app = new AppEntity();
        $this->app->setId('app-id');
        $this->app->setAppSecret('lksf#$osck$FSFDSF#$#F43jjidjsfisj-333');

        $this->querySigner = $this->getContainer()->get(QuerySigner::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testSignUri(): void
    {
        $signedUri = $this->querySigner->signUri('http://app.url/?foo=bar', $this->app, Context::createDefaultContext());
        parse_str($signedUri->getQuery(), $signedQuery);

        static::assertArrayHasKey('shop-id', $signedQuery);
        $shopConfig = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);
        static::assertIsArray($shopConfig);
        static::assertArrayHasKey('value', $shopConfig);
        $shopId = $shopConfig['value'];
        static::assertIsString($shopId);
        static::assertSame($shopId, $signedQuery['shop-id']);

        static::assertArrayHasKey('shop-url', $signedQuery);
        static::assertArrayHasKey('app_url', $shopConfig);
        $shopUrl = $shopConfig['app_url'];
        static::assertIsString($shopUrl);
        static::assertSame($shopUrl, $signedQuery['shop-url']);

        static::assertArrayHasKey('timestamp', $signedQuery);

        static::assertArrayHasKey('sw-version', $signedQuery);
        static::assertSame($this->getContainer()->getParameter('kernel.shopware_version'), $signedQuery['sw-version']);

        static::assertArrayHasKey('sw-context-language', $signedQuery);
        static::assertSame(Context::createDefaultContext()->getLanguageId(), $signedQuery['sw-context-language']);

        static::assertArrayHasKey('sw-user-language', $signedQuery);
        static::assertSame('en-GB', $signedQuery['sw-user-language']);

        static::assertNotNull($this->app->getAppSecret());

        static::assertArrayHasKey('shopware-shop-signature', $signedQuery);
        static::assertSame(
            \hash_hmac('sha256', Uri::withoutQueryValue($signedUri, 'shopware-shop-signature')->getQuery(), $this->app->getAppSecret()),
            $signedQuery['shopware-shop-signature']
        );
    }
}
