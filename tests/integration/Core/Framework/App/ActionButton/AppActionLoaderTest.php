<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppActionLoader;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ActionNotFoundException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class AppActionLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testCreateAppActionReturnCorrectData(): void
    {
        $actionLoader = static::getContainer()->get(AppActionLoader::class);

        /** @var EntityRepository<ActionButtonCollection> $actionRepo */
        $actionRepo = static::getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $actionCollection = $actionRepo->search($criteria, Context::createDefaultContext())->getEntities();
        $action = $actionCollection->first();
        static::assertNotNull($action);

        $shopIdProvider = static::getContainer()->get(ShopIdProvider::class);

        $ids = [Uuid::randomHex()];
        $result = $actionLoader->loadAppAction($action->getId(), $ids, Context::createDefaultContext());

        $app = $action->getApp();

        static::assertNotNull($app);

        $expected = [
            'source' => [
                'url' => getenv('APP_URL'),
                'appVersion' => $app->getVersion(),
                'shopId' => $shopIdProvider->getShopId(),
                'inAppPurchases' => null,
            ],
            'data' => [
                'ids' => $ids,
                'entity' => $action->getEntity(),
                'action' => $action->getAction(),
            ],
        ];

        static::assertEquals($expected, $result->asPayload());
        static::assertEquals($action->getUrl(), $result->getTargetUrl());
    }

    public function testThrowsIfAppUrlChangeWasDetected(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $actionLoader = static::getContainer()->get(AppActionLoader::class);

        /** @var EntityRepository<ActionButtonCollection> $actionRepo */
        $actionRepo = static::getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $actionCollection = $actionRepo->search($criteria, Context::createDefaultContext())->getEntities();
        $action = $actionCollection->first();
        static::assertNotNull($action);

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $ids = [Uuid::randomHex()];

        $this->expectException(ActionNotFoundException::class);
        $actionLoader->loadAppAction($action->getId(), $ids, Context::createDefaultContext());
    }

    public function testThrowsIfAppUrlChangeWasDetected67(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $actionLoader = static::getContainer()->get(AppActionLoader::class);

        /** @var EntityRepository<ActionButtonCollection> $actionRepo */
        $actionRepo = static::getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $actionCollection = $actionRepo->search($criteria, Context::createDefaultContext())->getEntities();
        $action = $actionCollection->first();
        static::assertNotNull($action);

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $ids = [Uuid::randomHex()];

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('The requested app action does not exist');
        $actionLoader->loadAppAction($action->getId(), $ids, Context::createDefaultContext());
    }
}
