<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppUrlChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class MoveShopPermanentlyStrategyTest extends TestCase
{
    use AppSystemTestBehaviour;
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;

    private ShopIdProvider $shopIdProvider;

    private Context $context;

    protected function setUp(): void
    {
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetName(): void
    {
        $moveShopPermanentlyResolver = $this->getContainer()->get(MoveShopPermanentlyStrategy::class);

        static::assertSame(
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
                static::callback(static fn (Manifest $manifest): bool => $manifest->getPath() === $appDir),
                $app->getId(),
                static::isType('string'),
                static::isInstanceOf(Context::class)
            );

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            $this->getAppLoader($appDir),
            $this->getContainer()->get('app.repository'),
            $registrationsService,
            $this->shopIdProvider
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertSame($shopId, $this->shopIdProvider->getShopId());

        // assert secret access key changed
        $updatedApp = $this->getInstalledApp($this->context);
        static::assertNotNull($app->getIntegration());
        static::assertNotNull($updatedApp->getIntegration());

        static::assertNotEquals(
            $app->getIntegration()->getSecretAccessKey(),
            $updatedApp->getIntegration()->getSecretAccessKey()
        );
    }

    public function testItIgnoresAppsWithoutSetup(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $shopId = $this->changeAppUrl();

        $registrationsService = $this->createMock(AppRegistrationService::class);
        $registrationsService->expects(static::never())
            ->method('registerApp');

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            $this->getAppLoader($appDir),
            $this->getContainer()->get('app.repository'),
            $registrationsService,
            $this->shopIdProvider
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertSame($shopId, $this->shopIdProvider->getShopId());
    }

    private function changeAppUrl(): string
    {
        $shopId = $this->shopIdProvider->getShopId();

        // create AppUrlChange
        $this->setEnvVars(['APP_URL' => 'https://test.new']);
        $wasThrown = false;

        try {
            $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException) {
            $wasThrown = true;
        }
        static::assertTrue($wasThrown);

        return $shopId;
    }

    private function getInstalledApp(Context $context): AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepo */
        $appRepo = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        $app = $appRepo->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }
}
