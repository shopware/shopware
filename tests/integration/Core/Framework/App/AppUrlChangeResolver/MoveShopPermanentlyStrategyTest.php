<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
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

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('refreshRegistration')
            ->with(
                $app,
                static::isInstanceOf(Context::class)
            );

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            static::getContainer()->get('app.repository'),
            $appManager,
            $this->shopIdProvider,
            new NullLogger()
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItIgnoresAppsWithoutSetup(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $shopId = $this->changeAppUrl(false);

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())
            ->method('refreshRegistration');

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            static::getContainer()->get('app.repository'),
            $appManager,
            $this->shopIdProvider,
            new NullLogger()
        );

        $moveShopPermanentlyResolver->resolve($this->context);

        static::assertSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItContinuesWithOtherAppsWhenOneReregisterFails(): void
    {
        $testAppDir = (string) realpath(__DIR__ . '/../Manifest/_fixtures/test');
        $testApp = $this->createAppEntity('test', 'app-1');
        $withConfigApp = $this->createAppEntity('withConfig', 'app-2');

        // a registered app must exist, otherwise no shop id change is suggested and the strategy returns early
        $this->loadAppsFromDir($testAppDir);
        $this->changeAppUrl();

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function () use (&$calls): void {
                ++$calls;

                if ($calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        $appRepository = new StaticEntityRepository([
            new AppCollection([$testApp, $withConfigApp]),
        ]);

        $moveShopPermanentlyResolver = new MoveShopPermanentlyStrategy(
            $appRepository,
            $appManager,
            $this->shopIdProvider,
            new NullLogger()
        );

        $this->expectExceptionObject(AppException::shopMoveFailed(['test']));

        $moveShopPermanentlyResolver->resolve($this->context);
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

    private function createAppEntity(string $name, string $id): AppEntity
    {
        $app = new AppEntity();
        $app->setId($id);
        $app->setName($name);

        return $app;
    }
}
