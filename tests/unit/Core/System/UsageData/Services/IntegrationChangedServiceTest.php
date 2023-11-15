<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider as AppSystemShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentReporter;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Services\IntegrationChangedService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Services\IntegrationChangedService
 */
class IntegrationChangedServiceTest extends TestCase
{
    use EnvTestBehaviour;

    protected function setUp(): void
    {
        $this->setEnvVars(['APP_URL' => 'test-shop.de']);
    }

    public function testItReturnsIfNoConsentIsGiven(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')->willReturn(false);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::never())
            ->method('get');

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfNoIntegrationInSystemConfigIsGiven(): void
    {
        $systemConfigService = $this->configureSystemConfig();
        $systemConfigService->delete(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION);

        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([[]])
        ));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $service = new IntegrationChangedService(
            new StaticEntityRepository([]),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfAppUrlHasNotChanged(): void
    {
        $systemConfigService = $this->configureSystemConfig();

        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([[]])
        ));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $service = new IntegrationChangedService(
            new StaticEntityRepository([]),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfNoIntegrationIsGiven(): void
    {
        $systemConfigService = $this->configureSystemConfig();

        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([[]])
        ));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $integrationRepository = new StaticEntityRepository([new IntegrationCollection()]);

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItUpdatesAppUrl(): void
    {
        $systemConfigService = $this->configureSystemConfig(integrationAppUrl: 'foobar');

        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([[]])
        ));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::once())
            ->method('updateConsentIntegrationAppUrl')
            ->with(
                'shopId',
                [
                    'accessKey' => 'accessKey',
                    'secretAccessKey' => 'secretAccessKey',
                    'appUrl' => EnvironmentHelper::getVariable('APP_URL'),
                ],
            );

        $integrationEntity = new IntegrationEntity();
        $integrationEntity->setId('id');
        $integrationEntity->setUniqueIdentifier('id');
        $integrationEntity->setAccessKey('accessKey');
        $integrationEntity->setSecretAccessKey('secretAccessKey');

        $integrationRepository = new StaticEntityRepository([new IntegrationCollection([$integrationEntity])]);

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        $service->checkAndHandleIntegrationChanged();

        static::assertEquals([
            'integrationId' => $integrationEntity->getId(),
            'appUrl' => EnvironmentHelper::getVariable('APP_URL'),
            'shopId' => 'shopId',
        ], $systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION));
    }

    public function testItDoesNotUpdateAppUrlIfReportingToTheGatewayFails(): void
    {
        $systemConfigService = $this->configureSystemConfig(
            integrationId: 'foobar',
            integrationAppUrl: 'oldUrl'
        );

        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([[]])
        ));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::once())
            ->method('updateConsentIntegrationAppUrl')
            ->with(
                'shopId',
                [
                    'accessKey' => 'accessKey',
                    'secretAccessKey' => 'secretAccessKey',
                    'appUrl' => EnvironmentHelper::getVariable('APP_URL'),
                ],
            )->willThrowException(new \Exception('reporting failed'));

        $integrationEntity = new IntegrationEntity();
        $integrationEntity->setId('id');
        $integrationEntity->setUniqueIdentifier('id');
        $integrationEntity->setAccessKey('accessKey');
        $integrationEntity->setSecretAccessKey('secretAccessKey');

        $integrationRepository = new StaticEntityRepository([new IntegrationCollection([$integrationEntity])]);

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        static::expectException(\Exception::class);
        static::expectExceptionMessage('reporting failed');
        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfShopIdHasNotChanged(): void
    {
        $systemConfigService = $this->configureSystemConfig(integrationAppUrl: 'oldUrl');

        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([['integrationId']])
        ));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('revokeConsent');

        $service = new IntegrationChangedService(
            new StaticEntityRepository([new IntegrationCollection()]),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
            $this->createMock(Connection::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItResetsUsageDataState(): void
    {
        $systemConfigService = $this->configureSystemConfig(shopId: 'newShopId');
        $shopIdProvider = new ShopIdProvider(new AppSystemShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([[]])
        ));

        $integrationRepository = new StaticEntityRepository([]);

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $consentService = new ConsentService(
            $systemConfigService,
            new StaticEntityRepository([]),
            new StaticEntityRepository([new UserConfigCollection([])]),
            $integrationRepository,
            $this->createMock(ConsentReporter::class),
            $shopIdProvider,
            new MockClock(),
            $appUrl
        );

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects(static::once())
            ->method('resetLastRunDateForAllEntities');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(static::once())
            ->method('delete')
            ->with('usage_data_entity_deletion');

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $entityDispatchService,
            $connection,
        );

        $service->checkAndHandleIntegrationChanged();

        static::assertNull($systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE));
        static::assertNull($systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION));
        static::assertFalse($systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED));
    }

    private function configureSystemConfig(
        string $shopId = 'shopId',
        string $appUrl = 'test-shop.de',
        string|null $integrationId = 'integrationId',
        string $integrationAppUrl = 'test-shop.de',
        string $integrationShopId = 'shopId'
    ): StaticSystemConfigService {
        return new StaticSystemConfigService([
            AppSystemShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY => [
                'value' => $shopId,
                'app_url' => $appUrl,
            ],
            ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION => [
                'integrationId' => $integrationId,
                'appUrl' => $integrationAppUrl,
                'shopId' => $integrationShopId,
            ],
            ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED => false,
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);
    }
}
