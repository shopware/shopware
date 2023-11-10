<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Services\IntegrationChangedService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Services\IntegrationChangedService
 */
class IntegrationChangedServiceTest extends TestCase
{
    public function testItReturnsIfNoConsentIsGiven(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')->willReturn(false);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::never())
            ->method('get');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfNoIntegrationInSystemConfigIsGiven(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(null);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfAppUrlHasNotChanged(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => 'foobar',
                    'appUrl' => EnvironmentHelper::getVariable('APP_URL'), // already the new url --> should not be reported and updated
                    'shopId' => 'shopId',
                ]
            );
        $systemConfigService->expects(static::never())
            ->method('set');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfNoIntegrationIdIsGiven(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => null,
                    'appUrl' => 'oldUrl',
                    'shopId' => 'shopId',
                ]
            );

        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::never())
            ->method('search');

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $this->createMock(ShopIdProvider::class),
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfNoIntegrationIsGiven(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('updateConsentIntegrationAppUrl');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => 'foobar',
                    'appUrl' => 'oldUrl',
                    'shopId' => 'shopId',
                ]
            );

        $integrationSearchResult = $this->createMock(EntitySearchResult::class);
        $integrationSearchResult->expects(static::once())
            ->method('first')
            ->willReturn(null);

        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::once())
            ->method('search')
            ->with(static::callback(function ($criteria) {
                static::assertInstanceOf(Criteria::class, $criteria);
                static::assertSame(['foobar'], $criteria->getIds());

                return true;
            }), static::isInstanceOf(Context::class))
            ->willReturn($integrationSearchResult);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItUpdatesAppUrl(): void
    {
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
        $integrationEntity->setAccessKey('accessKey');
        $integrationEntity->setSecretAccessKey('secretAccessKey');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => 'foobar',
                    'appUrl' => 'oldUrl',
                    'shopId' => 'shopId',
                ]
            );
        $systemConfigService->expects(static::once())
            ->method('set')
            ->with(
                ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION,
                [
                    'integrationId' => $integrationEntity->getId(),
                    'appUrl' => EnvironmentHelper::getVariable('APP_URL'),
                    'shopId' => 'shopId',
                ]
            );

        $integrationSearchResult = $this->createMock(EntitySearchResult::class);
        $integrationSearchResult->expects(static::once())
            ->method('first')
            ->willReturn($integrationEntity);

        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::once())
            ->method('search')
            ->willReturn($integrationSearchResult);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItDoesNotUpdateAppUrlIfReportingToTheGatewayFails(): void
    {
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

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => 'foobar',
                    'appUrl' => 'oldUrl',
                ]
            );

        $integrationEntity = new IntegrationEntity();
        $integrationEntity->setId('id');
        $integrationEntity->setAccessKey('accessKey');
        $integrationEntity->setSecretAccessKey('secretAccessKey');

        $integrationSearchResult = $this->createMock(EntitySearchResult::class);
        $integrationSearchResult->expects(static::once())
            ->method('first')
            ->willReturn($integrationEntity);

        $integrationRepository = $this->createMock(EntityRepository::class);
        $integrationRepository->expects(static::once())
            ->method('search')
            ->willReturn($integrationSearchResult);
        $integrationRepository->expects(static::never())
            ->method('update');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $integrationRepository,
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        static::expectException(\Exception::class);
        static::expectExceptionMessage('reporting failed');
        $service->checkAndHandleIntegrationChanged();
    }

    public function testItReturnsIfNoShopIdHasNotChanged(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::never())
            ->method('revokeConsent');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => 'integrationId',
                    'appUrl' => 'oldUrl',
                    'shopId' => 'shopId',
                ]
            );

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects(static::once())
            ->method('getShopId')
            ->willReturn('shopId');

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $this->createMock(EntityDispatchService::class),
        );

        $service->checkAndHandleIntegrationChanged();
    }

    public function testItResetsUsageDataState(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('isConsentAccepted')
            ->willReturn(true);
        $consentService->expects(static::once())
            ->method('revokeConsent');
        $consentService->expects(static::once())
            ->method('resetIsBannerHiddenToFalseForAllUsers');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION)
            ->willReturn(
                [
                    'integrationId' => 'integrationId',
                    'appUrl' => 'oldUrl',
                    'shopId' => 'shopId',
                ]
            );
        $systemConfigService->expects(static::once())
            ->method('delete')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE);
        $systemConfigService->expects(static::once())
            ->method('set')
            ->with(ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED, false);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects(static::once())
            ->method('getShopId')
            ->willReturn('newShopId');

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects(static::once())
            ->method('resetLastRunDateForAllEntities');

        $service = new IntegrationChangedService(
            $this->createMock(EntityRepository::class),
            $systemConfigService,
            $consentService,
            $shopIdProvider,
            $entityDispatchService,
        );

        $service->checkAndHandleIntegrationChanged();
    }
}
