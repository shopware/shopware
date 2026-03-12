<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;

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
        $this->shopIdProvider = static::getContainer()->get(ShopIdProvider::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetName(): void
    {
        $moveShopPermanentlyResolver = static::getContainer()->get(MoveShopPermanentlyStrategy::class);

        static::assertSame(
            MoveShopPermanentlyStrategy::STRATEGY_NAME,
            $moveShopPermanentlyResolver->getName()
        );
        static::assertIsString($moveShopPermanentlyResolver->getDescription());
    }

    public function testItReRegistersInstalledApps(): void
    {
        $appDir = (string) realpath(__DIR__ . '/../Manifest/_fixtures/test');
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp($this->context);

        $shopId = $this->changeAppUrl();

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('rotateNow')
            ->with(
                $app->getId(),
                static::isInstanceOf(Context::class),
                AppSecretRotationService::TRIGGER_SHOP_MOVE
            );

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(['test' => new Filesystem($appDir)]),
            static::getContainer()->get('app.repository'),
            $rotationService,
            $this->shopIdProvider
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItIgnoresAppsWithoutSetup(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $shopId = $this->changeAppUrl(false);

        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->never())
            ->method('rotateNow');

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(['no-setup' => new Filesystem($appDir)]),
            static::getContainer()->get('app.repository'),
            $rotationService,
            $this->shopIdProvider
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    private function changeAppUrl(bool $expectsToThrow = true): string
    {
        $shopId = $this->shopIdProvider->getShopId();

        // create AppUrlChange
        $this->setEnvVars(['APP_URL' => 'https://test.new']);
        $wasThrown = false;

        try {
            $this->shopIdProvider->reset();
            $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException) {
            $wasThrown = true;
        }
        static::assertSame($expectsToThrow, $wasThrown);

        return $shopId->id;
    }

    private function getInstalledApp(Context $context): AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepo */
        $appRepo = static::getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        $app = $appRepo->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }
}
