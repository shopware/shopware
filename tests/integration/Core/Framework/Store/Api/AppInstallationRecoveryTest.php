<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Api;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\Services\StoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class AppInstallationRecoveryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private const APP_DIR = __DIR__ . '/../../App/Manifest/_fixtures/test';

    private const APP_NAME = 'test';

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private AppFixture $appFixture;

    private Context $context;

    private ExtensionStoreActionsController $controller;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');

        /** @var AppFixture $appFixture */
        $appFixture = static::getContainer()->get(AppFixture::class);
        $this->appFixture = $appFixture;
        $this->context = Context::createDefaultContext();
        $this->controller = $this->createController();

        static::getContainer()->get(ShopIdProvider::class)->getShopId();
    }

    public function testAdministrationInstallRecoversPendingCredentialsWithoutReplayingLifecycle(): void
    {
        $app = $this->createApp('current-secret', 'pending-secret');
        $this->appendHandshake('recovered-secret');
        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_OK));

        $installedEvents = 0;
        $listener = static function (AppInstalledEvent $event) use (&$installedEvents): void {
            ++$installedEvents;
        };
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $eventDispatcher->addListener(AppInstalledEvent::class, $listener);

        try {
            static::assertSame(
                Response::HTTP_NO_CONTENT,
                $this->controller->installExtension('app', self::APP_NAME, $this->context)->getStatusCode()
            );
        } finally {
            $eventDispatcher->removeListener(AppInstalledEvent::class, $listener);
        }

        $recovered = $this->appFixture->getApp($app->getId());
        static::assertSame('recovered-secret', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
        static::assertFalse($recovered->isActive());
        static::assertSame(0, $installedEvents);
    }

    public function testAdministrationInstallFallsBackToCommittedSecret(): void
    {
        $app = $this->createApp('current-secret', 'pending-secret');

        $this->appendHandshake('minted-from-pending');
        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_FORBIDDEN));
        $this->appendHandshake('minted-from-current');
        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_OK));

        static::assertSame(
            Response::HTTP_NO_CONTENT,
            $this->controller->installExtension('app', self::APP_NAME, $this->context)->getStatusCode()
        );

        $recovered = $this->appFixture->getApp($app->getId());
        static::assertSame('minted-from-current', $recovered->getAppSecret());
        static::assertNull($recovered->getUnconfirmedAppSecrets());
    }

    public function testAdministrationInstallKeepsCandidatesWhenRetryIsAmbiguous(): void
    {
        $app = $this->createApp('current-secret', 'pending-secret');
        $this->appendHandshake('minted-recovery');
        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_INTERNAL_SERVER_ERROR));

        try {
            $this->controller->installExtension('app', self::APP_NAME, $this->context);
            static::fail('An ambiguous recovery must surface through the Administration installation API.');
        } catch (AppRegistrationException $e) {
            static::assertSame(AppException::REGISTRATION_FAILED, $e->getErrorCode());
        }

        $pending = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $pending->getAppSecret());
        static::assertSame(['minted-recovery', 'pending-secret'], $pending->getUnconfirmedAppSecrets());
    }

    public function testAdministrationInstallRetainsStateWhenAllCandidatesAreRejected(): void
    {
        $app = $this->createApp('current-secret', 'pending-secret');

        $this->appendHandshake('minted-from-pending');
        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_FORBIDDEN));
        $this->appendHandshake('minted-from-current');
        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_FORBIDDEN));

        try {
            $this->controller->installExtension('app', self::APP_NAME, $this->context);
            static::fail('Rejected candidates must surface as a typed Administration installation failure.');
        } catch (AppException $e) {
            static::assertSame(AppException::APP_SECRET_RECOVERY_FAILED, $e->getErrorCode());
        }

        $pending = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $pending->getAppSecret());
        static::assertSame(['pending-secret'], $pending->getUnconfirmedAppSecrets());
    }

    public function testAdministrationInstallRetainsNormalAlreadyInstalledBehaviour(): void
    {
        $app = $this->createApp('current-secret');
        $requestsBefore = $this->getRequestCount();

        try {
            $this->controller->installExtension('app', self::APP_NAME, $this->context);
            static::fail('An established app without pending credentials must remain already installed.');
        } catch (AppAlreadyInstalledException $e) {
            static::assertSame(AppException::ALREADY_INSTALLED, $e->getErrorCode());
        }

        static::assertSame($requestsBefore, $this->getRequestCount());
        $unchanged = $this->appFixture->getApp($app->getId());
        static::assertSame('current-secret', $unchanged->getAppSecret());
        static::assertNull($unchanged->getUnconfirmedAppSecrets());
    }

    private function createController(): ExtensionStoreActionsController
    {
        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $storeLifecycle = new StoreAppLifecycleService(
            static::createStub(StoreClient::class),
            new AppLoader(\dirname(self::APP_DIR), new NullLogger()),
            static::getContainer()->get(AppLifecycle::class),
            static::createStub(AppStorage::class),
            $salesChannelRepository,
            null,
            static::createStub(AppConfirmationDeltaProvider::class),
        );
        $extensionLifecycle = new ExtensionLifecycleService(
            $storeLifecycle,
            static::createStub(PluginService::class),
            static::createStub(PluginLifecycleService::class),
            static::createStub(PluginManagementService::class),
        );

        return new ExtensionStoreActionsController(
            $extensionLifecycle,
            static::createStub(ExtensionDownloader::class),
            static::createStub(PluginService::class),
            static::createStub(PluginManagementService::class),
            new Filesystem(),
            true,
        );
    }

    private function appendHandshake(string $appSecret): void
    {
        $manifest = Manifest::createFromXmlFile(self::APP_DIR . '/manifest.xml');
        $setup = $manifest->getSetup();
        static::assertNotNull($setup);
        $secret = $setup->getSecret();
        static::assertNotNull($secret);

        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
        $proof = hash_hmac(
            'sha256',
            $shopId . $_SERVER['APP_URL'] . $manifest->getMetadata()->getName(),
            $secret
        );

        $this->appendNewResponse(new GuzzleResponse(Response::HTTP_OK, [], json_encode([
            'proof' => $proof,
            'secret' => $appSecret,
            'confirmation_url' => 'https://example.com/confirm',
        ], \JSON_THROW_ON_ERROR)));
    }

    private function createApp(string $appSecret, ?string $pendingSecret = null): AppEntity
    {
        $app = $this->appFixture->createApp(
            $this->appFixture->loadManifest(self::APP_DIR . '/manifest.xml'),
            $appSecret,
        );

        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($app, $appSecret, $pendingSecret): void {
            $this->appRepository->update([[
                'id' => $app->getId(),
                'appSecret' => $appSecret,
                'unconfirmedAppSecrets' => $pendingSecret === null ? null : [$pendingSecret],
                'active' => false,
            ]], $context);
        });

        return $app;
    }
}
