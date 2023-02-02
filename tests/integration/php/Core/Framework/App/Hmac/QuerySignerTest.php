<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class QuerySignerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $secret;

    private QuerySigner $querySigner;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->secret = 'lksf#$osck$FSFDSF#$#F43jjidjsfisj-333';
        $this->querySigner = $this->getContainer()->get(QuerySigner::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testSignUri(): void
    {
        $signedUri = $this->querySigner->signUri('http://app.url/?foo=bar', $this->secret, Context::createDefaultContext());
        parse_str($signedUri->getQuery(), $signedQuery);

        static::assertArrayHasKey('shop-id', $signedQuery);
        $shopConfig = $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);
        static::assertIsArray($shopConfig);
        static::assertArrayHasKey('value', $shopConfig);
        $shopId = $shopConfig['value'];
        static::assertIsString($shopId);
        static::assertEquals($shopId, $signedQuery['shop-id']);

        static::assertArrayHasKey('shop-url', $signedQuery);
        static::assertArrayHasKey('app_url', $shopConfig);
        $shopUrl = $shopConfig['app_url'];
        static::assertIsString($shopUrl);
        static::assertEquals($shopUrl, $signedQuery['shop-url']);

        static::assertArrayHasKey('timestamp', $signedQuery);

        static::assertArrayHasKey('sw-version', $signedQuery);
        static::assertEquals($this->getContainer()->getParameter('kernel.shopware_version'), $signedQuery['sw-version']);

        static::assertArrayHasKey('sw-context-language', $signedQuery);
        static::assertEquals(Context::createDefaultContext()->getLanguageId(), $signedQuery['sw-context-language']);

        static::assertArrayHasKey('sw-user-language', $signedQuery);
        static::assertEquals('en-GB', $signedQuery['sw-user-language']);

        static::assertArrayHasKey('shopware-shop-signature', $signedQuery);
        static::assertEquals(
            hash_hmac('sha256', Uri::withoutQueryValue($signedUri, 'shopware-shop-signature')->getQuery(), $this->secret),
            $signedQuery['shopware-shop-signature']
        );
    }
}
