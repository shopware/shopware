<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StoreApiInfoController::class)]
#[Group('store-api')]
class StoreApiInfoControllerTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use SalesChannelApiTestBehaviour;

    public function testFetchStoreApiRoutes(): void
    {
        $client = $this->getSalesChannelBrowser();
        $client->request('GET', '/store-api/_info/routes');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(200, $client->getResponse()->getStatusCode());

        $routes = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        foreach ($routes['endpoints'] as $route) {
            static::assertArrayHasKey('path', $route);
            static::assertArrayHasKey('methods', $route);
        }
    }
}
