<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Store\Event\FirstRunWizardStartedEvent;
use Shopware\Core\Framework\Store\Exception\LicenseDomainVerificationException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Services\TrackingEventClient;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\DomainVerificationRequestStruct;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Shopware\Core\Framework\Store\Struct\LicenseDomainStruct;
use Shopware\Core\Framework\Store\Struct\PluginRegionStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Store\Struct\StorePluginStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(FirstRunWizardService::class)]
class FirstRunWizardServiceTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new AdminApiSource(Uuid::randomHex()));
    }

    public function testTracksAndDispatchesEventWhenFrwIsStarted(): void
    {
        $firstRunWizardStartedEvent = new FirstRunWizardStartedEvent(FrwState::openState(), $this->context);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with($firstRunWizardStartedEvent);

        $trackingEventClient = $this->createMock(TrackingEventClient::class);
        $trackingEventClient->expects(static::once())
            ->method('fireTrackingEvent')
            ->with('First Run Wizard started');

        $frwService = $this->createFirstRunWizardService(
            eventDispatcher: $eventDispatcher,
            trackingEventClient: $trackingEventClient,
        );

        $frwService->startFrw($this->context);
    }

    public function testFrwLoginFailsIfContextSourceIsNotAdminApi(): void
    {
        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->method('frwLogin')
            ->willThrowException(new InvalidContextSourceException(AdminApiSource::class, SystemSource::class));

        $frwService = new FirstRunWizardService(
            $this->createMock(StoreService::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(FilesystemOperator::class),
            true,
            $this->createMock(EventDispatcherInterface::class),
            $frwClient,
            $this->createMock(EntityRepository::class),
            $this->createMock(TrackingEventClient::class),
        );

        $this->expectException(InvalidContextSourceException::class);

        $frwService->frwLogin(
            'shopwareId',
            'password',
            Context::createDefaultContext(),
        );
    }

    public function testSuccessfulFrwLoginStoresFrwUserToken(): void
    {
        $firstRunWizardUserToken = [
            'firstRunWizardUserToken' => [
                'token' => 'frw-us3r-t0k3n',
                'expirationDate' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('frwLogin')
            ->with('shopwareId', 'password', $this->context)
            ->willReturn($firstRunWizardUserToken);

        $source = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects(static::once())
            ->method('upsert')
            ->with([
                [
                    'id' => null,
                    'userId' => $source->getUserId(),
                    'key' => FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN,
                    'value' => [
                        FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN => $firstRunWizardUserToken['firstRunWizardUserToken']['token'],
                    ],
                ],
            ]);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
            userConfigRepository: $userRepository,
        );

        $frwService->frwLogin(
            'shopwareId',
            'password',
            $this->context,
        );
    }

    public function testUpgradeAccessTokenFailsIfContextSourceIsNotAdminApi(): void
    {
        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('upgradeAccessToken')
            ->willThrowException(new \RuntimeException());

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $this->expectException(\RuntimeException::class);

        $frwService->upgradeAccessToken(Context::createDefaultContext());
    }

    public function testSuccessfulUpgradeAccessTokenDeletesFrwTokenAndStoresStoreToken(): void
    {
        $shopUserTokenStruct = new ShopUserTokenStruct(
            'shop-us3r-t0k3n',
            new \DateTimeImmutable('2022-12-15'),
        );

        $shopUserTokenResponse = [
            'shopUserToken' => [
                'token' => $shopUserTokenStruct->getToken(),
                'expirationDate' => $shopUserTokenStruct->getExpirationDate()->format(Defaults::STORAGE_DATE_FORMAT),
            ],
            'shopSecret' => 'shop-s3cr3t',
        ];

        $accessTokenStruct = new AccessTokenStruct(
            $shopUserTokenStruct,
            $shopUserTokenResponse['shopSecret']
        );

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('upgradeAccessToken')
            ->willReturn($shopUserTokenResponse);

        $storeService = $this->createMock(StoreService::class);
        $storeService->expects(static::once())
            ->method('updateStoreToken')
            ->with($this->context, $accessTokenStruct);

        $source = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);

        $userConfigRepository = $this->createMock(EntityRepository::class);
        $userConfigRepository->expects(static::once())
            ->method('searchIds')
            ->willReturn(
                new IdSearchResult(
                    1,
                    [['primaryKey' => $source->getUserId(), 'data' => []]],
                    new Criteria(),
                    $this->context,
                ),
            );
        $userConfigRepository->expects(static::once())
            ->method('delete');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('set')
            ->with(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET, $accessTokenStruct->getShopSecret());

        $frwService = $this->createFirstRunWizardService(
            storeService: $storeService,
            systemConfigService: $systemConfigService,
            frwClient: $frwClient,
            userConfigRepository: $userConfigRepository,
        );

        $frwService->upgradeAccessToken($this->context);
    }

    public function testFrwShouldNotRunIfAutoRunIsDisabled(): void
    {
        $frwService = $this->createFirstRunWizardService(
            autoRun: false,
        );

        static::assertFalse($frwService->frwShouldRun());
    }

    public function testFrwShouldNotRunIfStatusIsCompleted(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('getString')
            ->with('core.frw.completedAt')
            ->willReturn((new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
        );

        static::assertFalse($frwService->frwShouldRun());
    }

    public function testFrwShouldNotRunIfStatusIsFailedAndFailureCountIsAboveThreshold(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            'core.frw.failureCount' => 4,
            'core.frw.completedAt' => '',
            'core.frw.failedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
        );

        static::assertFalse($frwService->frwShouldRun());
    }

    public function testFrwShouldRunIfStatusIsFailedAndFailureCountIsUnderThreshold(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            'core.frw.failureCount' => 3,
            'core.frw.completedAt' => '',
            'core.frw.failedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
        );

        static::assertTrue($frwService->frwShouldRun());
    }

    public function testVerifiesNewLicenseDomain(): void
    {
        $domain = 'shopware.swag';
        $edition = 'Community Edition';

        $domainVerificationRequestStruct = new DomainVerificationRequestStruct(
            'v3r1f1c4t10n-s3cr3t',
            'sw-domain-hash.html'
        );

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::exactly(2))
            ->method('getLicenseDomains')
            ->willReturnOnConsecutiveCalls(
                // The first request will return an empty collection
                [],
                // The second request will return the newly created license domain
                [
                    [
                        'domain' => $domain,
                        'edition' => [
                            'label' => $edition,
                        ],
                        'verified' => true,
                    ],
                ],
            );
        $frwClient->expects(static::once())
            ->method('fetchVerificationInfo')
            ->willReturn([
                'content' => $domainVerificationRequestStruct->getContent(),
                'fileName' => $domainVerificationRequestStruct->getFileName(),
            ]);
        $frwClient->expects(static::once())
            ->method('checkVerificationSecret')
            ->with($domain, $this->context);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(static::once())
            ->method('write')
            ->with(
                $domainVerificationRequestStruct->getFileName(),
                $domainVerificationRequestStruct->getContent(),
            );

        $systemConfigService = new StaticSystemConfigService([]);
        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
            filesystemOperator: $filesystem,
            frwClient: $frwClient,
        );

        $frwService->verifyLicenseDomain($domain, $this->context);

        static::assertSame($domain, $systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN));
        static::assertSame($edition, $systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_EDITION));
    }

    public function testThrowsExceptionIfNewLicenseDomainIsNotVerified(): void
    {
        $domain = 'shopware.swag';
        $edition = 'Community Edition';

        $domainVerificationRequestStruct = new DomainVerificationRequestStruct(
            'v3r1f1c4t10n-s3cr3t',
            'sw-domain-hash.html'
        );

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::exactly(2))
            ->method('getLicenseDomains')
            ->willReturnOnConsecutiveCalls(
                // The first request will return an empty collection
                [],
                // The second request will return the newly created license domain
                [
                    [
                        'domain' => $domain,
                        'edition' => [
                            'label' => $edition,
                        ],
                        'verified' => false,
                    ],
                ],
            );
        $frwClient->expects(static::once())
            ->method('fetchVerificationInfo')
            ->willReturn([
                'content' => $domainVerificationRequestStruct->getContent(),
                'fileName' => $domainVerificationRequestStruct->getFileName(),
            ]);
        $frwClient->expects(static::once())
            ->method('checkVerificationSecret')
            ->with($domain, $this->context);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(static::once())
            ->method('write')
            ->with(
                $domainVerificationRequestStruct->getFileName(),
                $domainVerificationRequestStruct->getContent(),
            );

        $systemConfigService = new StaticSystemConfigService([]);

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
            filesystemOperator: $filesystem,
            frwClient: $frwClient,
        );

        $this->expectException(LicenseDomainVerificationException::class);

        $frwService->verifyLicenseDomain($domain, $this->context);
        static::assertEmpty($systemConfigService->all());
    }

    public function testThrowsExceptionIfVerificationSecretCanNotBeStoredOnFilesystem(): void
    {
        $domain = 'shopware.swag';

        $domainVerificationRequestStruct = new DomainVerificationRequestStruct(
            'v3r1f1c4t10n-s3cr3t',
            'sw-domain-hash.html'
        );

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::exactly(1))
            ->method('getLicenseDomains')
            ->willReturn([]);
        $frwClient->expects(static::once())
            ->method('fetchVerificationInfo')
            ->willReturn([
                'content' => $domainVerificationRequestStruct->getContent(),
                'fileName' => $domainVerificationRequestStruct->getFileName(),
            ]);
        $frwClient->expects(static::never())
            ->method('checkVerificationSecret');

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(static::once())
            ->method('write')
            ->willThrowException(new UnableToWriteFile());

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::never())
            ->method('set');

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
            filesystemOperator: $filesystem,
            frwClient: $frwClient,
        );

        $this->expectException(LicenseDomainVerificationException::class);
        $this->expectExceptionMessage(sprintf('License host verification failed for domain "%s."', $domain));

        $frwService->verifyLicenseDomain($domain, $this->context);
    }

    public function testIdentifiesAndConvertsCurrentLicenseDomains(): void
    {
        $licenseDomains = [
            [
                'id' => 1,
                'domain' => 'xn--tst-qla.de',
                'verified' => true,
                'edition' => [
                    'name' => 'Community Edition',
                    'label' => 'Community Edition',
                ],
            ],
            [
                'id' => 2,
                'domain' => 'shopware.swag',
                'verified' => true,
                'edition' => [
                    'name' => 'Community Edition',
                    'label' => 'Community Edition',
                ],
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getLicenseDomains')
            ->willReturn($licenseDomains);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('getString')
            ->with(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN)
            ->willReturn('täst.de');

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
            frwClient: $frwClient,
        );

        $licenseDomains = $frwService->getLicenseDomains($this->context);

        $currentLicenseDomain = $licenseDomains->first();
        static::assertInstanceOf(LicenseDomainStruct::class, $currentLicenseDomain);
        static::assertEquals('täst.de', $currentLicenseDomain->getDomain());
        static::assertTrue($currentLicenseDomain->isActive());

        $otherLicenseDomain = $licenseDomains->last();
        static::assertInstanceOf(LicenseDomainStruct::class, $otherLicenseDomain);
        static::assertEquals('shopware.swag', $otherLicenseDomain->getDomain());
        static::assertFalse($otherLicenseDomain->isActive());
    }

    public function testRecommendationRegions(): void
    {
        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getRecommendationRegions')
            ->willReturn([
                [
                    'label' => 'Germany / Austria / Switzerland',
                    'name' => 'dach',
                    'categories' => [
                        [
                            'name' => 'payment',
                            'label' => 'Payment',
                        ],
                        [
                            'name' => 'ERP',
                            'label' => 'erp',
                        ],
                    ],
                ],
                [
                    'label' => 'United States',
                    'name' => 'us',
                    'categories' => [
                        [
                            'name' => 'Tools',
                            'label' => 'tools',
                        ],
                        [
                            'name' => 'Marketing Automation',
                            'label' => 'marketing_automation',
                        ],
                        [
                            'name' => 'Legal',
                            'label' => 'legal',
                        ],
                    ],
                ],
            ]);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $recommendationRegions = $frwService->getRecommendationRegions($this->context);
        static::assertCount(2, $recommendationRegions);

        $dachRegion = $recommendationRegions->first();
        static::assertInstanceOf(PluginRegionStruct::class, $dachRegion);
        static::assertCount(2, $dachRegion->getCategories());

        $usRegion = $recommendationRegions->last();
        static::assertInstanceOf(PluginRegionStruct::class, $usRegion);
        static::assertCount(3, $usRegion->getCategories());
    }

    public function testFiltersOutCategoriesWithMissingNameOrLabel(): void
    {
        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getRecommendationRegions')
            ->willReturn([
                [
                    'label' => 'Germany / Austria / Switzerland',
                    'name' => 'dach',
                    'categories' => [
                        [
                            'name' => 'payment',
                            'label' => '',
                        ],
                        [
                            'name' => 'ERP',
                            'label' => 'erp',
                        ],
                    ],
                ],
                [
                    'label' => 'United States',
                    'name' => 'us',
                    'categories' => [
                        [
                            'name' => '',
                            'label' => 'tools',
                        ],
                        [
                            'name' => 'Marketing Automation',
                            'label' => 'marketing_automation',
                        ],
                        [
                            'name' => 'Legal',
                            'label' => 'legal',
                        ],
                    ],
                ],
            ]);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $recommendationRegions = $frwService->getRecommendationRegions($this->context);
        static::assertCount(2, $recommendationRegions);

        $dachRegion = $recommendationRegions->first();
        static::assertInstanceOf(PluginRegionStruct::class, $dachRegion);
        static::assertCount(1, $dachRegion->getCategories());

        $usRegion = $recommendationRegions->last();
        static::assertInstanceOf(PluginRegionStruct::class, $usRegion);
        static::assertCount(2, $usRegion->getCategories());
    }

    public function testFiltersOutRecommendationRegionWithMissingNameOrLabel(): void
    {
        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getRecommendationRegions')
            ->willReturn([
                [
                    'label' => 'Germany / Austria / Switzerland',
                    'name' => '',
                    'categories' => [],
                ],
                [
                    'label' => '',
                    'name' => 'us',
                    'categories' => [],
                ],
            ]);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $recommendationRegions = $frwService->getRecommendationRegions($this->context);
        static::assertCount(0, $recommendationRegions);
    }

    public function testGetRecommendedPlugins(): void
    {
        $recommendations = [
            [
                'name' => 'SwagPaypal',
                'label' => 'PayPal',
                'localizedInfo' => [
                    'name' => 'PayPal',
                    'shortDescription' => 'PayPal plugin',
                ],
            ],
            [
                'name' => 'SwagMollie',
                'label' => 'Mollie',
                'localizedInfo' => [
                    'name' => 'Mollie',
                    'shortDescription' => 'Mollie plugin',
                ],
            ],
            [
                'name' => 'SwagKlarna',
                'label' => 'Klarna',
                'localizedInfo' => [
                    'name' => 'Klarna',
                    'shortDescription' => 'Klarna plugin',
                ],
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getRecommendations')
            ->with('us', 'payment', $this->context)
            ->willReturn($recommendations);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $recommendations = $frwService->getRecommendations(
            new PluginCollection(),
            new AppCollection(),
            'us',
            'payment',
            $this->context
        );

        static::assertCount(3, $recommendations);
    }

    public function testFiltersOutRecommendedPluginsWithMissingName(): void
    {
        $recommendations = [
            [
                'name' => 'SwagPaypal',
                'label' => 'PayPal',
                'localizedInfo' => [
                    'name' => 'PayPal',
                    'shortDescription' => 'PayPal plugin',
                ],
            ],
            [
                'name' => '',
                'label' => 'SwagMollie',
                'localizedInfo' => [
                    'name' => 'Mollie',
                    'shortDescription' => 'Mollie plugin',
                ],
            ],
            [
                'name' => 'SwagKlarna',
                'label' => 'Klarna',
                'localizedInfo' => [
                    'name' => '',
                    'shortDescription' => 'Klarna plugin',
                ],
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getRecommendations')
            ->with('us', 'payment', $this->context)
            ->willReturn($recommendations);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $recommendations = $frwService->getRecommendations(
            new PluginCollection(),
            new AppCollection(),
            'us',
            'payment',
            $this->context
        );

        static::assertCount(1, $recommendations);
    }

    public function testAddsInformationToAlreadyInstalledPlugin(): void
    {
        $recommendations = [
            [
                'name' => 'SwagPaypal',
                'type' => 'plugin',
                'label' => 'PayPal',
                'localizedInfo' => [
                    'name' => 'PayPal',
                    'shortDescription' => 'PayPal plugin',
                ],
            ],
            [
                'name' => 'SwagMollie',
                'type' => 'plugin',
                'label' => 'Mollie',
                'localizedInfo' => [
                    'name' => 'Mollie',
                    'shortDescription' => 'Mollie plugin',
                ],
            ],
            [
                'name' => 'SwagKlarna',
                'type' => 'app',
                'label' => 'Klarna',
                'localizedInfo' => [
                    'name' => 'Klarna',
                    'shortDescription' => 'Klarna plugin',
                ],
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getRecommendations')
            ->with('us', 'payment', $this->context)
            ->willReturn($recommendations);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $installedPlugin = (new PluginEntity())->assign([
            'id' => Uuid::randomHex(),
            'name' => 'SwagPaypal',
            'active' => true,
            'installedAt' => new \DateTimeImmutable(),
        ]);

        $recommendations = $frwService->getRecommendations(
            new PluginCollection([$installedPlugin]),
            new AppCollection(),
            'us',
            'payment',
            $this->context
        );

        static::assertCount(3, $recommendations);

        $paypalPlugin = $recommendations->filter(static fn (StorePluginStruct $plugin) => $plugin->getName() === 'SwagPaypal')->first();
        static::assertInstanceOf(StorePluginStruct::class, $paypalPlugin);
        static::assertTrue($paypalPlugin->isActive());
    }

    public function testUpdatesFrwStateToFailedOnFrwFinishWithoutTrackingEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(FirstRunWizardFinishedEvent::class));

        $trackingEventClient = $this->createMock(TrackingEventClient::class);
        $trackingEventClient->expects(static::never())
            ->method('fireTrackingEvent');

        $systemConfigService = new StaticSystemConfigService();
        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
            eventDispatcher: $eventDispatcher,
            trackingEventClient: $trackingEventClient,
        );

        $frwService->finishFrw(true, $this->context);

        static::assertSame(1, $systemConfigService->get('core.frw.failureCount'));
    }

    public function testUpdatesFrwStateToCompletedOnFrwFinishWithTrackingEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(FirstRunWizardFinishedEvent::class));

        $trackingEventClient = $this->createMock(TrackingEventClient::class);
        $trackingEventClient->expects(static::once())
            ->method('fireTrackingEvent');

        $systemConfigService = new StaticSystemConfigService();

        $frwService = $this->createFirstRunWizardService(
            systemConfigService: $systemConfigService,
            eventDispatcher: $eventDispatcher,
            trackingEventClient: $trackingEventClient,
        );

        $frwService->finishFrw(false, $this->context);

        static::assertNull($systemConfigService->get('core.frw.successCount'));
    }

    public function testGetLanguagePlugins(): void
    {
        $languagePlugins = [
            [
                'id' => 123456,
                'name' => 'SwagLanguagePack',
                'label' => 'Shopware Language Pack',
                'iconPath' => 'https://example.com/icon.png',
                'localizedInfo' => [
                    'name' => 'Shopware Language Pack',
                    'shortDescription' => 'Shopware Language Pack',
                ],
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getLanguagePlugins')
            ->willReturn($languagePlugins);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $languagePlugins = $frwService->getLanguagePlugins(new PluginCollection(), new AppCollection(), $this->context);
        static::assertCount(1, $languagePlugins);
    }

    public function testGetDemodataPlugins(): void
    {
        $demodataPlugins = [
            [
                'id' => 123456,
                'name' => 'SwagDemodata',
                'label' => 'Shopware Demodata Plugin',
                'iconPath' => 'https://example.com/icon.png',
                'localizedInfo' => [
                    'name' => 'Shopware Demodata Plugin',
                    'shortDescription' => 'Shopware Demodata Plugin',
                ],
            ],
        ];

        $frwClient = $this->createMock(FirstRunWizardClient::class);
        $frwClient->expects(static::once())
            ->method('getDemoDataPlugins')
            ->willReturn($demodataPlugins);

        $frwService = $this->createFirstRunWizardService(
            frwClient: $frwClient,
        );

        $demodataPlugins = $frwService->getDemoDataPlugins(new PluginCollection(), new AppCollection(), $this->context);
        static::assertCount(1, $demodataPlugins);
    }

    private function createFirstRunWizardService(
        ?StoreService $storeService = null,
        ?SystemConfigService $systemConfigService = null,
        ?FilesystemOperator $filesystemOperator = null,
        ?bool $autoRun = true,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?FirstRunWizardClient $frwClient = null,
        ?EntityRepository $userConfigRepository = null,
        ?TrackingEventClient $trackingEventClient = null,
    ): FirstRunWizardService {
        return new FirstRunWizardService(
            $storeService ?? $this->createMock(StoreService::class),
            $systemConfigService ?? $this->createMock(SystemConfigService::class),
            $filesystemOperator ?? $this->createMock(FilesystemOperator::class),
            $autoRun ?? true,
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            $frwClient ?? $this->createMock(FirstRunWizardClient::class),
            $userConfigRepository ?? $this->createMock(EntityRepository::class),
            $trackingEventClient ?? $this->createMock(TrackingEventClient::class),
        );
    }
}
