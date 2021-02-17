<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppActionLoader;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopware\Core\Framework\App\Exception\ActionNotFoundException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class AppActionLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use SystemConfigTestBehaviour;

    public function testCreateAppActionReturnCorrectData(): void
    {
        $actionLoader = $this->getContainer()->get(AppActionLoader::class);

        /** @var EntityRepository $actionRepo */
        $actionRepo = $this->getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $actionCollection = $actionRepo->search($criteria, Context::createDefaultContext());
        /** @var ActionButtonEntity $action */
        $action = $actionCollection->first();

        $shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);

        $ids = [Uuid::randomHex()];
        $result = $actionLoader->loadAppAction($action->getId(), $ids, Context::createDefaultContext());

        $expected = [
            'source' => [
                'url' => getenv('APP_URL'),
                'appVersion' => $action->getApp()->getVersion(),
                'shopId' => $shopIdProvider->getShopId(),
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
        $actionLoader = $this->getContainer()->get(AppActionLoader::class);

        /** @var EntityRepository $actionRepo */
        $actionRepo = $this->getContainer()->get('app_action_button.repository');
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addAssociation('app')
            ->addAssociation('app.integration');

        $actionCollection = $actionRepo->search($criteria, Context::createDefaultContext());
        /** @var ActionButtonEntity $action */
        $action = $actionCollection->first();

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $ids = [Uuid::randomHex()];

        static::expectException(ActionNotFoundException::class);
        $actionLoader->loadAppAction($action->getId(), $ids, Context::createDefaultContext());
    }
}
