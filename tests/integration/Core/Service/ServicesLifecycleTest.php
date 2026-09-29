<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Service\AppInfo;
use Shopware\Core\Service\DTO\Service;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Permission\PermissionsConsent;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceSourceResolver;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[Package('framework')]
class ServicesLifecycleTest extends TestCase
{
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;

    private const REGISTRY_URL = 'https://registry.services.example.com';

    private const SERVICES_ENDPOINT = self::REGISTRY_URL . '/api/service/';

    private const SELECT_APP_ENDPOINT = self::REGISTRY_URL . '/api/distribution/service/select-app/';

    private const APP_ZIP_ENDPOINT = self::REGISTRY_URL . '/api/distribution/service/app-zip/';

    private const ONE = 'ExampleOneService';

    private const TWO = 'ExampleTwoService';

    private const REVISION_HASH = '9f2c1b7ad4e35608c1de92f470ab35d1';

    private const CONTENT_HASH = '3ab5f0c9e71d42568b0ac3fd19e77b24';

    private const CONFIG_KEY_CONSENT = 'core.services.permissionsConsent';

    private LifecycleManager $manager;

    private ServiceStorage $storage;

    private PermissionsService $permissions;

    private SystemConfigService $config;

    private Connection $connection;

    private Context $context;

    /**
     * @var list<array<string, mixed>>
     */
    private array $listed;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $appInfo;

    /**
     * @var array<string, \Closure(): MockResponse>
     */
    private array $responses;

    protected function setUp(): void
    {
        $this->bootKernelWithServicesEnabled();
        $this->stubRegistry();
        $this->serveAppFilesFromFixtures();

        $container = static::getContainer();
        $this->manager = $container->get(LifecycleManager::class);
        $this->storage = $container->get(ServiceStorage::class);
        $this->permissions = $container->get(PermissionsService::class);
        $this->config = $container->get(SystemConfigService::class);
        $this->connection = $container->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * Steps:
     * 1. Log into the account, grant consent.
     * 2. Reconcile against a registry listing both services.
     *
     * Expected:
     * - Both are installed active at the published revision.
     * - Each holds its manifest privileges; nothing is left requested.
     */
    public function testReconcileInstallsListedServicesActiveWithTheirPrivilegesGranted(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();

        static::assertSame([self::ONE, self::TWO], $this->manager->reconcile($this->context));

        $one = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertTrue($one->active);
        static::assertSame(['order:read'], $one->privileges);
        static::assertSame([], $one->requestedPrivileges);
        static::assertSame(['shopware_account'], $one->requirements);

        $two = $this->assertInstalled(self::TWO, '1.2.0');
        static::assertTrue($two->active);
        static::assertEqualsCanonicalizing(['product:read', 'order:read'], $two->privileges);
        static::assertSame([], $two->requestedPrivileges);
        static::assertSame(['service_consent'], $two->requirements);
    }

    /**
     * Steps:
     * 1. Registry lists one service that requires services to be enabled.
     * 2. Disable services, reconcile.
     *
     * Expected:
     * - Nothing is installed.
     */
    public function testReconcileSkipsInstallationWhileServicesAreDisabled(): void
    {
        $this->listed = [self::registryEntry(name: self::TWO, label: 'Example Two', host: 'https://two.services.example.com')];
        $this->appInfo[self::TWO] = self::appInfo(name: self::TWO, version: '1.2.0', requirements: ['services_enabled']);
        $this->manager->disable($this->context);

        static::assertSame([], $this->manager->reconcile($this->context));
        static::assertNull($this->storage->findByName(self::TWO, $this->context));
    }

    /**
     * Steps:
     * 1. Registry lists one service with a requirement this shop does not know.
     * 2. Reconcile.
     *
     * Expected:
     * - Nothing is installed.
     */
    public function testReconcileSkipsInstallationForAnUnknownRequirement(): void
    {
        $this->listed = [self::registryEntry(name: self::TWO, label: 'Example Two', host: 'https://two.services.example.com')];
        $this->appInfo[self::TWO] = self::appInfo(name: self::TWO, version: '1.2.0', requirements: ['example_unknown_requirement']);

        static::assertSame([], $this->manager->reconcile($this->context));
        static::assertNull($this->storage->findByName(self::TWO, $this->context));
    }

    /**
     * Steps:
     * 1. Install both services.
     * 2. Registry publishes a new revision of the consent-bound service, requirements unchanged.
     * 3. Reconcile.
     *
     * Expected:
     * - Same app row at the new revision, privileges still granted.
     * - The other service is untouched.
     */
    public function testReconcileUpdatesACompatibleRevisionInPlace(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->manager->reconcile($this->context);
        $two = $this->assertInstalled(self::TWO, '1.2.0');

        $this->appInfo[self::TWO] = self::appInfo(name: self::TWO, version: '1.3.0', requirements: ['service_consent']);

        $this->manager->reconcile($this->context);

        $updated = $this->assertInstalled(self::TWO, '1.3.0');
        static::assertSame($two->id, $updated->id);
        static::assertEqualsCanonicalizing(['product:read', 'order:read'], $updated->privileges);
        static::assertSame([], $updated->requestedPrivileges);

        $one = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame(['order:read'], $one->privileges);
    }

    /**
     * Steps:
     * 1. Install both services.
     * 2. Registry publishes a revision of the consent-bound service that requires services enabled.
     * 3. Disable services, reconcile.
     *
     * Expected:
     * - The consent-bound service is uninstalled.
     * - The account-bound service keeps its privileges.
     */
    public function testReconcileUninstallsWhenThePublishedRevisionClosesTheInstallationGate(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->manager->reconcile($this->context);
        $this->assertInstalled(self::TWO, '1.2.0');

        $this->appInfo[self::TWO] = self::appInfo(name: self::TWO, version: '1.3.0', requirements: ['services_enabled']);
        $this->manager->disable($this->context);

        $this->manager->reconcile($this->context);

        static::assertNull($this->storage->findByName(self::TWO, $this->context));
        $one = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame(['order:read'], $one->privileges);
    }

    /**
     * Steps:
     * 1. Install both services.
     * 2. Registry publishes a revision of the consent-bound service with a requirement this shop does not know.
     * 3. Reconcile.
     *
     * Expected:
     * - The consent-bound service is uninstalled.
     * - The account-bound service keeps its privileges.
     */
    public function testReconcileUninstallsWhenThePublishedRevisionRequiresSomethingUnknown(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->manager->reconcile($this->context);
        $this->assertInstalled(self::TWO, '1.2.0');

        $this->appInfo[self::TWO] = self::appInfo(name: self::TWO, version: '1.3.0', requirements: ['example_unknown_requirement']);

        $this->manager->reconcile($this->context);

        static::assertNull($this->storage->findByName(self::TWO, $this->context));
        $one = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame(['order:read'], $one->privileges);
    }

    /**
     * Steps:
     * 1. Registry lists the second service as requiring services enabled; log in, reconcile.
     * 2. Disable services.
     *
     * Expected:
     * - The service requiring services enabled is uninstalled without a reconcile.
     * - The account-bound service stays installed with its privileges.
     */
    public function testDisablingServicesUninstallsTheServicesThatRequireThem(): void
    {
        $this->appInfo[self::TWO] = self::appInfo(name: self::TWO, version: '1.2.0', requirements: ['services_enabled']);
        $this->logIntoShopwareAccount();
        $this->manager->reconcile($this->context);
        $this->assertInstalled(self::TWO, '1.2.0');

        $this->manager->disable($this->context);

        static::assertNull($this->storage->findByName(self::TWO, $this->context));
        $one = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame(['order:read'], $one->privileges);
    }

    /**
     * Steps:
     * 1. Install both services.
     * 2. Log out without the logout event, reconcile.
     * 3. Log back in without the login event, reconcile.
     *
     * Expected:
     * - Logged out: privileges revoked and requested again; the service stays active.
     * - Logged in: privileges granted again.
     */
    public function testReconcileRepairsMissedAccountChangesWithoutARevisionChange(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->manager->reconcile($this->context);
        $installed = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame(['order:read'], $installed->privileges);

        $this->logOutOfShopwareAccount();
        $this->manager->reconcile($this->context);

        $drifted = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame([], $drifted->privileges);
        static::assertEqualsCanonicalizing($installed->privileges, $drifted->requestedPrivileges);
        static::assertTrue($drifted->active);

        $this->logIntoShopwareAccount();
        $this->manager->reconcile($this->context);

        $repaired = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertEqualsCanonicalizing($installed->privileges, $repaired->privileges);
        static::assertSame([], $repaired->requestedPrivileges);
        static::assertTrue($repaired->active);
    }

    /**
     * Steps:
     * 1. Install both services.
     * 2. Drop consent without the revoke event, reconcile.
     * 3. Restore consent without the grant event, reconcile.
     *
     * Expected:
     * - Dropped: privileges revoked and requested again.
     * - Restored: privileges granted again.
     */
    public function testReconcileRepairsMissedConsentChangesWithoutARevisionChange(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->manager->reconcile($this->context);
        $installed = $this->assertInstalled(self::TWO, '1.2.0');
        static::assertEqualsCanonicalizing(['product:read', 'order:read'], $installed->privileges);

        $this->dropConsentWithoutEvents();
        $this->manager->reconcile($this->context);

        $drifted = $this->assertInstalled(self::TWO, '1.2.0');
        static::assertSame([], $drifted->privileges);
        static::assertEqualsCanonicalizing($installed->privileges, $drifted->requestedPrivileges);

        $this->restoreConsentWithoutEvents();
        $this->manager->reconcile($this->context);

        $repaired = $this->assertInstalled(self::TWO, '1.2.0');
        static::assertEqualsCanonicalizing($installed->privileges, $repaired->privileges);
        static::assertSame([], $repaired->requestedPrivileges);
    }

    /**
     * Steps:
     * 1. Log into the account, grant consent.
     * 2. Registry lists the unsupported service first; its app info answers 422.
     * 3. Reconcile twice.
     *
     * Expected:
     * - The supported service is installed with its privileges on the first pass.
     * - The unsupported one is not installed and is asked for again on the second pass.
     */
    public function testAnUnsupportedServiceDoesNotBlockTheNextServiceAndIsRetried(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->listed = array_reverse($this->listed);
        $attempts = 0;
        $this->responses[self::SELECT_APP_ENDPOINT . self::TWO] = static function () use (&$attempts): JsonMockResponse {
            ++$attempts;

            return new JsonMockResponse([
                'errors' => [
                    'type' => 'unsupported_platform_version',
                    'detail' => 'No supported app version for Shopware platform version "6.7.0.0"',
                    'available_versions' => ['6.8.0.0'],
                ],
            ], ['http_code' => 422]);
        };

        static::assertSame([self::ONE], $this->manager->reconcile($this->context));

        $one = $this->assertInstalled(self::ONE, '1.0.0');
        static::assertSame(['order:read'], $one->privileges);
        static::assertNull($this->storage->findByName(self::TWO, $this->context));

        $this->manager->reconcile($this->context);

        static::assertSame(2, $attempts);
    }

    /**
     * Steps:
     * 1. Install both services.
     * 2. Reconcile again while counting permission updates.
     *
     * Expected:
     * - Nothing is reported installed, no permission update fires.
     * - Both services are unchanged: id, active, version, privileges.
     */
    public function testAConvergedReconcileIsANoOp(): void
    {
        $this->logIntoShopwareAccount();
        $this->grantConsent();
        $this->manager->reconcile($this->context);
        $one = $this->assertInstalled(self::ONE, '1.0.0');
        $two = $this->assertInstalled(self::TWO, '1.2.0');

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $permissionEvents = 0;
        $this->addEventListener($dispatcher, AppPermissionsUpdated::class, static function () use (&$permissionEvents): void {
            ++$permissionEvents;
        });

        static::assertSame([], $this->manager->reconcile($this->context));
        static::assertSame(0, $permissionEvents);

        foreach ([$one, $two] as $before) {
            $after = $this->storage->findByName($before->name, $this->context);
            static::assertNotNull($after);
            static::assertSame($before->id, $after->id);
            static::assertSame($before->active, $after->active);
            static::assertSame($before->version, $after->version);
            static::assertSame($before->privileges, $after->privileges);
            static::assertSame($before->requestedPrivileges, $after->requestedPrivileges);
        }
    }

    /**
     * ENABLE_SERVICES defaults to "auto", which is off outside prod, and a booted container keeps the
     * value it resolved.
     */
    private function bootKernelWithServicesEnabled(): void
    {
        $this->setEnvVars(['ENABLE_SERVICES' => 'true']);
        KernelLifecycleManager::bootKernel();
    }

    private function stubRegistry(): void
    {
        $this->listed = [
            self::registryEntry(name: self::ONE, label: 'Example One', host: 'https://one.services.example.com'),
            self::registryEntry(name: self::TWO, label: 'Example Two', host: 'https://two.services.example.com'),
        ];
        $this->appInfo = [
            self::ONE => self::appInfo(name: self::ONE, version: '1.0.0', requirements: ['shopware_account']),
            self::TWO => self::appInfo(name: self::TWO, version: '1.2.0', requirements: ['service_consent']),
        ];
        $this->responses = [
            self::SERVICES_ENDPOINT => fn () => new JsonMockResponse([
                'services' => $this->listed,
                'pagination' => ['page' => 1, 'total-pages' => 1, 'pages' => 1, 'total' => \count($this->listed), 'limit' => 10],
            ]),
            self::SELECT_APP_ENDPOINT . self::ONE => fn () => new JsonMockResponse($this->appInfo[self::ONE]),
            self::SELECT_APP_ENDPOINT . self::TWO => fn () => new JsonMockResponse($this->appInfo[self::TWO]),
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $endpoint = explode('?', $url)[0];

            return isset($this->responses[$endpoint])
                ? ($this->responses[$endpoint])()
                : new MockResponse('', ['http_code' => 404]);
        });
        $registry = new Client(self::REGISTRY_URL, 'https://shop.example.com', $httpClient);

        $container = static::getContainer();
        $container->set(Client::class, $registry);
        $shopwareVersion = $container->getParameter('kernel.shopware_version');
        static::assertIsString($shopwareVersion);
        $container->set(ServiceClientFactory::class, new ServiceClientFactory($httpClient, $registry, $shopwareVersion));
    }

    private function serveAppFilesFromFixtures(): void
    {
        static::getContainer()->set(ServiceSourceResolver::class, new FixtureServiceSourceResolver());
    }

    private function grantConsent(): void
    {
        $this->permissions->grant('2026-09-09', Context::createDefaultContext(new AdminApiSource('test-user')));
    }

    /**
     * Writes the config directly so no PermissionsRevokedEvent fires: the drift has to survive until
     * reconcile runs.
     */
    private function dropConsentWithoutEvents(): void
    {
        $this->config->delete(self::CONFIG_KEY_CONSENT);
    }

    private function restoreConsentWithoutEvents(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-consent',
            revision: '2026-09-09',
            consentingUserId: 'test-user',
            grantedAt: new \DateTimeImmutable('2026-09-09T00:00:00+00:00'),
        );
        $this->config->set(self::CONFIG_KEY_CONSENT, json_encode($consent, \JSON_THROW_ON_ERROR));
    }

    /**
     * Writes the token directly: there is no domain login without the store API, and no login/logout
     * event fires, which the account drift test relies on.
     */
    private function logIntoShopwareAccount(): void
    {
        $this->connection->executeStatement('UPDATE `user` SET store_token = :token', ['token' => 'test-token']);
    }

    private function logOutOfShopwareAccount(): void
    {
        $this->connection->executeStatement('UPDATE `user` SET store_token = NULL');
    }

    private function assertInstalled(string $name, string $version): Service
    {
        $service = $this->storage->findByName($name, $this->context);
        static::assertNotNull($service, \sprintf('Expected service "%s" to be installed', $name));
        static::assertSame(\sprintf('%s-%s', $version, self::REVISION_HASH), $service->version);

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    private static function registryEntry(string $name, string $label, string $host): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'host' => $host,
            'app-endpoint' => self::SELECT_APP_ENDPOINT . $name,
            'license-sync-endpoint' => '/api/service/license/commercial/sync',
            'activate-on-install' => true,
        ];
    }

    /**
     * @param list<string> $requirements
     *
     * @return array<string, mixed>
     */
    private static function appInfo(string $name, string $version, array $requirements): array
    {
        return [
            'app-version' => $version,
            'app-revision' => \sprintf('%s-%s', $version, self::REVISION_HASH),
            'app-hash' => self::CONTENT_HASH,
            'app-hash-algorithm' => 'xxh128',
            'app-min-shop-supported-version' => '6.7.0.0',
            'app-zip-url' => \sprintf('%s%s/%s', self::APP_ZIP_ENDPOINT, $name, self::REVISION_HASH),
            'app-requirements' => $requirements,
        ];
    }
}

/**
 * Uses committed app files while retaining real service source selection.
 *
 * @internal
 */
class FixtureServiceSourceResolver extends ServiceSourceResolver
{
    public function __construct()
    {
    }

    public function filesystem(Manifest|AppEntity $app): Filesystem
    {
        return self::fixture($app instanceof Manifest ? $app->getMetadata()->getName() : $app->getName());
    }

    public function filesystemForVersion(AppInfo $appInfo): Filesystem
    {
        return self::fixture($appInfo->name);
    }

    private static function fixture(string $name): Filesystem
    {
        return new Filesystem(\sprintf('%s/_fixtures/%s', __DIR__, $name));
    }
}
