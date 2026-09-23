<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppSecretRotationServiceTest extends TestCase
{
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;
    use IntegrationTestBehaviour;

    private AppSecretRotationService $service;

    private Context $context;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    /**
     * @var EntityRepository<IntegrationCollection>
     */
    private EntityRepository $integrationRepository;

    protected function setUp(): void
    {
        $this->service = static::getContainer()->get(AppSecretRotationService::class);
        $this->context = Context::createDefaultContext();
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->integrationRepository = static::getContainer()->get('integration.repository');
    }

    public function testRotateNowSuccessfullyRotatesAppSecret(): void
    {
        $appDir = __DIR__ . '/../Manifest/_fixtures/test';
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp();
        $integration = $app->getIntegration();
        static::assertNotNull($integration);

        $manifest = Manifest::createFromXmlFile($appDir . '/manifest.xml');
        $appSecret = 'new-app-secret';

        $this->appendNewResponse(new Response(200, [], $this->buildHandshakeResponse($manifest, $appSecret)));
        $this->appendNewResponse(new Response(200, []));

        $this->service->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);

        $updatedApp = $this->getInstalledApp();
        $updatedIntegration = $updatedApp->getIntegration();
        static::assertNotNull($updatedIntegration);

        $newIntegrationId = $updatedApp->getIntegrationId();
        static::assertIsString($newIntegrationId);
        $newSecretKey = $updatedIntegration->getSecretAccessKey();
        static::assertNotSame($integration->getSecretAccessKey(), $newSecretKey);
        static::assertNotSame($integration->getId(), $newIntegrationId);

        // Verify old integration was soft-deleted
        $criteria = new Criteria([$integration->getId()]);
        $oldIntegration = $this->integrationRepository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(IntegrationEntity::class, $oldIntegration);
        static::assertNotNull($oldIntegration->getDeletedAt());

        // Verify app secret was updated
        static::assertSame($appSecret, $updatedApp->getAppSecret());
    }

    public function testRotateNowWithAppWithoutSetupDoesNothing(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp();

        // Should not make any HTTP requests
        $this->service->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);

        static::assertSame(0, $this->getRequestCount());
    }

    public function testRotateNowRollsBackIntegrationOnDefinitiveRejection(): void
    {
        $appDir = __DIR__ . '/../Manifest/_fixtures/test';
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp();
        $manifest = Manifest::createFromXmlFile($appDir . '/manifest.xml');

        $integration = $app->getIntegration();
        static::assertNotNull($integration);
        $oldIntegrationId = $integration->getId();
        $originalAppSecret = $app->getAppSecret();

        $this->appendNewResponse(new Response(200, [], $this->buildHandshakeResponse($manifest, 'new-app-secret')));
        // Definitive rejection (4xx): the app did not switch, so roll the integration back.
        $this->appendNewResponse(new Response(403, []));

        try {
            $this->service->rotateNow($app->getId(), $this->context, AppSecretRotationService::TRIGGER_CLI);
            static::fail('Expected rotation to rethrow');
        } catch (\Throwable) {
            // expected
        }

        $rolledBack = $this->getInstalledApp();
        $rolledBackIntegration = $rolledBack->getIntegration();
        static::assertNotNull($rolledBackIntegration);

        static::assertSame($oldIntegrationId, $rolledBack->getIntegrationId());
        static::assertSame($integration->getSecretAccessKey(), $rolledBackIntegration->getSecretAccessKey());
        static::assertSame($originalAppSecret, $rolledBack->getAppSecret());
        static::assertNull($rolledBack->getUnconfirmedAppSecrets());
    }

    private function buildHandshakeResponse(Manifest $manifest, string $appSecret): string
    {
        $setup = $manifest->getSetup();
        static::assertNotNull($setup);
        $secret = $setup->getSecret();
        static::assertNotNull($secret);

        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
        $appName = $manifest->getMetadata()->getName();
        $proof = hash_hmac('sha256', $shopId . $_SERVER['APP_URL'] . $appName, $secret);

        return json_encode([
            'proof' => $proof,
            'secret' => $appSecret,
            'confirmation_url' => 'https://example.com/confirm',
        ], \JSON_THROW_ON_ERROR);
    }

    private function getInstalledApp(): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('integration');

        $app = $this->appRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }
}
