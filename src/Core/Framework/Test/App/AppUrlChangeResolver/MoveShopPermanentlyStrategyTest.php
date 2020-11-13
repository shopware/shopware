<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\AppUrlChangeResolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppUrlChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

class MoveShopPermanentlyStrategyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use EnvTestBehaviour;
    use AppSystemTestBehaviour;
    use SystemConfigTestBehaviour;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetName(): void
    {
        $moveShopPermanentlyResolver = $this->getContainer()->get(MoveShopPermanentlyStrategy::class);

        static::assertEquals(
            MoveShopPermanentlyStrategy::STRATEGY_NAME,
            $moveShopPermanentlyResolver->getName()
        );
        static::assertIsString($moveShopPermanentlyResolver->getDescription());
    }

    public function testItReRegistersInstalledApps(): void
    {
        $appDir = __DIR__ . '/../Manifest/_fixtures/test';
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp($this->context);

        $shopId = $this->changeAppUrl();

        $registrationsService = $this->createMock(AppRegistrationService::class);
        $registrationsService->expects(static::once())
            ->method('registerApp')
            ->with(
                static::callback(static function (Manifest $manifest) use ($appDir): bool {
                    return $manifest->getPath() === $appDir;
                }),
                $app->getId(),
                static::isType('string'),
                static::isInstanceOf(Context::class)
            );

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            new AppLoader(
                $appDir,
                $this->getContainer()->getParameter('kernel.project_dir'),
                $this->getContainer()->get(ConfigReader::class)
            ),
            $this->getContainer()->get('app.repository'),
            $registrationsService,
            $this->systemConfigService
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertEquals($shopId, $this->shopIdProvider->getShopId());
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY));

        // assert secret access key changed
        $updatedApp = $this->getInstalledApp($this->context);
        static::assertNotEquals(
            $app->getIntegration()->getSecretAccessKey(),
            $updatedApp->getIntegration()->getSecretAccessKey()
        );
    }

    public function testItIgnoresAppsWithoutSetup(): void
    {
        $shopId = $this->changeAppUrl();

        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $registrationsService = $this->createMock(AppRegistrationService::class);
        $registrationsService->expects(static::never())
            ->method('registerApp');

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            new AppLoader(
                $appDir,
                $this->getContainer()->getParameter('kernel.project_dir'),
                $this->getContainer()->get(ConfigReader::class)
            ),
            $this->getContainer()->get('app.repository'),
            $registrationsService,
            $this->systemConfigService
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertEquals($shopId, $this->shopIdProvider->getShopId());
        static::assertNull($this->systemConfigService->get(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY));
    }

    private function changeAppUrl(): string
    {
        $shopId = $this->shopIdProvider->getShopId();

        // create AppUrlChange
        $this->setEnvVars(['APP_URL' => 'https://test.new']);
        $wasThrown = false;

        try {
            $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            $wasThrown = true;
        }
        static::assertTrue($wasThrown);

        return $shopId;
    }

    private function getInstalledApp(Context $context): AppEntity
    {
        /** @var EntityRepositoryInterface $appRepo */
        $appRepo = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        $apps = $appRepo->search($criteria, $context);
        static::assertEquals(1, $apps->getTotal());

        return $apps->first();
    }
}
