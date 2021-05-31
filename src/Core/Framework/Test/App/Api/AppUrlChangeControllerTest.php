<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\AppUrlChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AppUrlChangeControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use SystemConfigTestBehaviour;

    public function testGetAvailableStrategies(): void
    {
        $url = '/api/app-system/app-url-change/strategies';
        $this->getBrowser()->request('GET', $url);
        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        $appUrlChangeResolver = $this->getContainer()->get(Resolver::class);
        static::assertEquals($appUrlChangeResolver->getAvailableStrategies(), $response);
    }

    public function testResolveWithExistingStrategy(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);

        $url = '/api/app-system/app-url-change/resolve';
        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [],
            json_encode(['strategy' => UninstallAppsStrategy::STRATEGY_NAME])
        );
        $response = $this->getBrowser()->getResponse()->getContent();

        static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode(), $response);
    }

    public function testResolveWithNotFoundStrategy(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);

        $url = '/api/app-system/app-url-change/resolve';
        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [],
            json_encode(['strategy' => 'test'])
        );
        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertEquals(400, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertCount(1, $response['errors']);
        static::assertEquals('Unable to find AppUrlChangeResolver with name: "test".', $response['errors'][0]['detail']);
    }

    public function testResolveWithoutStrategy(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);

        $url = '/api/app-system/app-url-change/resolve';
        $this->getBrowser()->request(
            'POST',
            $url
        );
        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertEquals(400, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertCount(1, $response['errors']);
        static::assertEquals('Parameter "strategy" is missing.', $response['errors'][0]['detail']);
    }

    public function testGetUrlDiff(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);

        $oldUrl = 'http://old.com';
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => $oldUrl,
            'value' => Uuid::randomHex(),
        ]);

        $url = '/api/app-system/app-url-change/url-difference';
        $this->getBrowser()->request('GET', $url);
        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertEquals(['oldUrl' => $oldUrl, 'newUrl' => $_SERVER['APP_URL']], $response);
    }

    public function testGetUrlDiffWithoutUrlChange(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $oldUrl = 'http://old.com';
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => $oldUrl,
            'value' => Uuid::randomHex(),
        ]);

        $url = '/api/app-system/app-url-change/url-difference';
        $this->getBrowser()->request('GET', $url);

        static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testGetUrlDiffWithTemporaryUrlChange(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        // Simulates that during the app_url was different during a webhook
        // but is now back to the old value
        $systemConfigService->set(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);
        $oldUrl = $_SERVER['APP_URL'];
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => $oldUrl,
            'value' => Uuid::randomHex(),
        ]);

        $url = '/api/app-system/app-url-change/url-difference';
        $this->getBrowser()->request('GET', $url);

        static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNull($systemConfigService->get(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY));
    }
}
