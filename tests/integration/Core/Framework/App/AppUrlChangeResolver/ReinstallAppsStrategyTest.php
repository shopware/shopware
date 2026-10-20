<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Integration\App\TestAppServer;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\PassingAppUrlVerifier;

/**
 * @internal
 */
#[Package('framework')]
class ReinstallAppsStrategyTest extends TestCase
{
    use AppSystemTestBehaviour;
    use EnvTestBehaviour;
    use GuzzleTestClientBehaviour;
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
        $reinstallAppsResolver = static::getContainer()->get(ReinstallAppsStrategy::class);

        static::assertSame(
            ReinstallAppsStrategy::STRATEGY_NAME,
            $reinstallAppsResolver->getName()
        );
        static::assertIsString($reinstallAppsResolver->getDescription());
    }

    public function testItReRegistersInstalledApps(): void
    {
        $appDir = (string) realpath(__DIR__ . '/../Manifest/_fixtures/test');
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp($this->context);

        $shopId = $this->changeAppUrl();

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('reregister')
            ->with(
                $app,
                static::isInstanceOf(Context::class)
            );

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            static::getContainer()->get('app.repository'),
            $appManager,
            $this->shopIdProvider,
            new NullLogger(),
            new PassingAppUrlVerifier(),
        );

        $reinstallAppsResolver->resolve($this->context);

        static::assertNotSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItDelegatesAppsWithoutSetupToAppManager(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $shopId = $this->changeAppUrl(false);

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->once())
            ->method('reregister');

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            static::getContainer()->get('app.repository'),
            $appManager,
            $this->shopIdProvider,
            new NullLogger(),
            new PassingAppUrlVerifier(),
        );

        $reinstallAppsResolver->resolve($this->context);

        static::assertNotSame($shopId, $this->shopIdProvider->getShopId()->id);
    }

    public function testItContinuesWithOtherAppsWhenOneReinstallFails(): void
    {
        $testApp = $this->createAppEntity('test', 'app-1');
        $withConfigApp = $this->createAppEntity('withConfig', 'app-2');

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function () use (&$calls): void {
                ++$calls;

                if ($calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        $appRepository = new StaticEntityRepository([
            new AppCollection([$testApp, $withConfigApp]),
        ]);

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $this->shopIdProvider,
            new NullLogger(),
            new PassingAppUrlVerifier(),
        );

        $this->expectExceptionObject(AppException::reinstallAppsFailed(['test']));

        $reinstallAppsResolver->resolve($this->context);
    }

    public function testSecretsHeldBeforeTheShopIdWasDeletedRecoverTheAppRegistration(): void
    {
        $this->loadAppsFromDir((string) realpath(__DIR__ . '/../Manifest/_fixtures/test'));
        $appId = $this->getInstalledApp($this->context)->getId();

        // the staging copy's registration: the app now trusts this secret, production holds another one
        $secretHeldByApp = 'secret-the-app-trusts';
        $this->setAppSecret($appId, $secretHeldByApp);
        $copyShopId = $this->shopIdProvider->getShopId()->id;

        // APP_URL is unchanged, so the ShopId check passes and the secrets are retained before the id is deleted
        static::getContainer()->get(ReinstallAppsStrategy::class)->resolve($this->context);

        static::assertNotSame($copyShopId, $this->shopIdProvider->getShopId()->id);
        $retained = static::getContainer()->get(AppRetainedSecretGateway::class)->findValid('test');
        static::assertSame([$copyShopId], array_values(array_unique(array_column($retained, 'shopId'))));
        static::assertContains($secretHeldByApp, array_column($retained, 'secret'));

        // production still holds its own, older secret and adopts the retained one
        $this->setAppSecret($appId, 'secret-production-holds');
        $requestsBefore = $this->getRequestCount();
        $recoveredSecret = 'secret-minted-by-recovery';
        $this->appendHandshakeMinting($recoveredSecret);
        $this->appendNewResponse(new Response(200));

        static::getContainer()->get(AppSecretRotationService::class)->rotateNow(
            $appId,
            $this->context,
            AppSecretRotationService::TRIGGER_CLI,
            [$secretHeldByApp]
        );

        $handshake = $this->getPastRequest($requestsBefore);
        static::assertSame(
            hash_hmac('sha256', $handshake->getUri()->getQuery(), $secretHeldByApp),
            $handshake->getHeaderLine('shopware-shop-signature')
        );
        static::assertSame($recoveredSecret, $this->getInstalledApp($this->context)->getAppSecret());
    }

    private function setAppSecret(string $appId, string $secret): void
    {
        $this->context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($appId, $secret): void {
            static::getContainer()->get('app.repository')->update([['id' => $appId, 'appSecret' => $secret]], $context);
        });
    }

    private function appendHandshakeMinting(string $mintedSecret): void
    {
        $proof = hash_hmac('sha256', $this->shopIdProvider->getShopId()->id . EnvironmentHelper::getVariable('APP_URL') . 'test', TestAppServer::TEST_SETUP_SECRET);

        $this->appendNewResponse(new Response(200, [], json_encode([
            'proof' => $proof,
            'secret' => $mintedSecret,
            'confirmation_url' => TestAppServer::CONFIRMATION_URL,
        ], \JSON_THROW_ON_ERROR)));
    }

    private function changeAppUrl(bool $expectToThrow = true): string
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
        static::assertSame($expectToThrow, $wasThrown);

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
