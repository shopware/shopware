<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppUrlChangeResolver\Resolver;
use Shopware\Core\Framework\App\AppUrlChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;

/**
 * @internal
 */
class AppUrlChangeControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testGetAvailableStrategies(): void
    {
        $url = '/api/app-system/app-url-change/strategies';
        $this->getBrowser()->request('GET', $url);
        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $appUrlChangeResolver = $this->getContainer()->get(Resolver::class);
        static::assertEquals($appUrlChangeResolver->getAvailableStrategies(), $response);
    }

    public function testResolveWithExistingStrategy(): void
    {
        $url = '/api/app-system/app-url-change/resolve';
        $json = \json_encode(['strategy' => UninstallAppsStrategy::STRATEGY_NAME]);
        static::assertNotFalse($json);
        static::assertJson($json);

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [],
            $json
        );

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertNotFalse($response);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode(), $response);
    }

    public function testResolveWithNotFoundStrategy(): void
    {
        $url = '/api/app-system/app-url-change/resolve';
        $json = \json_encode(['strategy' => 'test']);
        static::assertNotFalse($json);
        static::assertJson($json);

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [],
            $json
        );

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());
        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertCount(1, $response['errors']);
        static::assertSame('Unable to find AppUrlChangeResolver with name: "test".', $response['errors'][0]['detail']);
    }

    public function testResolveWithoutStrategy(): void
    {
        $url = '/api/app-system/app-url-change/resolve';

        $this->getBrowser()->request(
            'POST',
            $url
        );

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->getBrowser()->getResponse()->getStatusCode());

        static::assertCount(1, $response['errors']);
        static::assertSame('Parameter "strategy" is missing.', $response['errors'][0]['detail']);
    }

    public function testGetUrlDiffWithApps(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $oldUrl = 'http://old.com';
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => $oldUrl,
            'value' => Uuid::randomHex(),
        ]);

        $url = '/api/app-system/app-url-change/url-difference';
        $this->getBrowser()->request('GET', $url);

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertEquals(['oldUrl' => $oldUrl, 'newUrl' => $_SERVER['APP_URL']], $response);
    }

    public function testGetUrlDiffWithoutApps(): void
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $oldUrl = 'http://old.com';
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => $oldUrl,
            'value' => Uuid::randomHex(),
        ]);

        $url = '/api/app-system/app-url-change/url-difference';
        $this->getBrowser()->request('GET', $url);

        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode());
    }
}
