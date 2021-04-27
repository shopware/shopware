<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Store\Event\FirstRunWizardStartedEvent;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SystemConfigTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FirstRunWizardClientTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;
    use SystemConfigTestBehaviour;
    use EnvTestBehaviour;

    private Context $storeContext;

    private SystemConfigService $systemConfigService;

    private InstanceService $instanceService;

    public function setUp(): void
    {
        $this->storeContext = $this->createAdminStoreContext();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->instanceService = $this->getContainer()->get(InstanceService::class);

        $this->systemConfigService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, null);
    }

    public function testItFiresTrackingEvent(): void
    {
        $this->getRequestHandler()->append(new Response(200));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (FirstRunWizardStartedEvent $event): bool {
                return $event->getState()->isOpen();
            }));

        $frwClient = $this->getFwrClientForStateTests($eventDispatcher);

        $frwClient->startFrw($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => $this->instanceService->getInstanceId(),
                'additionalData' => ['shopwareVersion' => $this->instanceService->getShopwareVersion()],
                'event' => 'First Run Wizard started',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true)
        );
    }

    public function testItFiresTrackingEventIfFrwIsCompleted(): void
    {
        $this->getRequestHandler()->append(new Response(200));

        $this->systemConfigService->set(
            'core.frw.completedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (FirstRunWizardStartedEvent $event): bool {
                return $event->getState()->isCompleted();
            }));

        $frwClient = $this->getFwrClientForStateTests($eventDispatcher);

        $frwClient->startFrw($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => $this->instanceService->getInstanceId(),
                'additionalData' => ['shopwareVersion' => $this->instanceService->getShopwareVersion()],
                'event' => 'First Run Wizard started',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true)
        );
    }

    public function testItFiresTrackingEventIfFrwHasFailed(): void
    {
        $this->getRequestHandler()->append(new Response(200));

        $this->systemConfigService->set(
            'core.frw.failedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );
        $this->systemConfigService->set('core.frw.failureCount', 10);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (FirstRunWizardStartedEvent $event): bool {
                return $event->getState()->isFailed() && $event->getState()->getFailureCount() === 10;
            }));

        $frwClient = $this->getFwrClientForStateTests($eventDispatcher);

        $frwClient->startFrw($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => $this->instanceService->getInstanceId(),
                'additionalData' => ['shopwareVersion' => $this->instanceService->getShopwareVersion()],
                'event' => 'First Run Wizard started',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true)
        );
    }

    public function testFrwLoginMustBeInUserScope(): void
    {
        static::expectException(InvalidContextSourceException::class);
        $this->getSystemFwrClient()->frwLogin('1', 'shopware', 'en_GB', Context::createDefaultContext());
    }

    public function testFrwLogin(): void
    {
        $this->getRequestHandler()->append(new Response(200, [], \json_encode([
            'firstRunWizardUserToken' => [
                'token' => 'updatedToken',
                'expirationDate' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
            ],
        ])));

        $this->getSystemFwrClient()->frwLogin('1', 'shopware', 'en_GB', $this->storeContext);

        static::assertEquals('updatedToken', $this->getStoreTokenFromContext($this->storeContext));

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals('POST', $lastRequest->getMethod());
        static::assertEquals('/swplatform/firstrunwizard/login', $lastRequest->getUri()->getPath());

        static::assertEquals([
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => 'en_GB',
        ], Query::parse($lastRequest->getUri()->getQuery()));

        static::assertEquals([
            'shopwareId' => '1',
            'password' => 'shopware',
        ], \json_decode($lastRequest->getBody()->getContents(), true));

        static::assertEquals('updatedToken', $this->getStoreTokenFromContext($this->storeContext));

        static::assertEquals('1', $this->systemConfigService->get('core.store.shopwareId'));
    }

    public function testUpgradeAccessToken(): void
    {
        $this->setLicenseDomain('http://shop.de');

        $this->getRequestHandler()->append(
            new Response(200, [], \json_encode([
                'shopUserToken' => [
                    'token' => 'updatedToken',
                    'expirationDate' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
                ],
                'shopSecret' => 'this-shop-is-secret',
            ]))
        );

        $this->getSystemFwrClient()->upgradeAccessToken('en-GB', $this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals([
            'shopwareUserId' => $this->storeContext->getSource()->getUserId(),
        ], \json_decode($lastRequest->getBody()->getContents(), true));

        static::assertEquals('this-shop-is-secret', $this->systemConfigService->get('core.store.shopSecret'));
        static::assertEquals('updatedToken', $this->getStoreTokenFromContext($this->storeContext));
    }

    public function testFinishFrwUpdatesFrwStateIfCompleted(): void
    {
        $this->getRequestHandler()->append(new Response(200));

        $this->resetFrwState();

        $mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockEventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(
                static function (FirstRunWizardFinishedEvent $event): bool {
                    return $event->getState()->isCompleted() && $event->getPreviousState()->isOpen();
                }
            ));

        $frwClient = $this->getFwrClientForStateTests($mockEventDispatcher);
        $frwClient->finishFrw(false, $this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => $this->instanceService->getInstanceId(),
                'additionalData' => ['shopwareVersion' => $this->instanceService->getShopwareVersion()],
                'event' => 'First Run Wizard finished',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true)
        );
    }

    public function testItUpdatesFailureStateIfFrwEventFailed(): void
    {
        $this->resetFrwState();
        $this->systemConfigService->set('core.frw.failureCount', 2);
        $this->systemConfigService->set('core.frw.failedAt', (new \DateTimeImmutable())->format(\DateTime::ATOM));

        $mockEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockEventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(
                static function (FirstRunWizardFinishedEvent $event): bool {
                    return $event->getState()->isFailed()
                        && $event->getState()->getFailureCount() === 3
                        && $event->getPreviousState()->getFailureCount() === 2
                        && $event->getPreviousState()->isFailed();
                }
            ));

        $frwClient = $this->getFwrClientForStateTests($mockEventDispatcher);

        $frwClient->finishFrw(true, $this->storeContext);
    }

    public function testShouldRunIsAlwaysFalseIfFrwAutoRunIsFalse(): void
    {
        $frwClient = $this->getFrWClientWithAutoRunSettings(false);
        static::assertFalse($frwClient->frwShouldRun());
    }

    public function testShouldRunIsFalseIfFrwIsCompleted(): void
    {
        $frwClient = $this->getFrWClientWithAutoRunSettings(true);

        $this->systemConfigService->set(
            'core.frw.completedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );

        static::assertFalse($frwClient->frwShouldRun());
    }

    public function testShouldRunIsTrueIfFrwDidNotStartYet(): void
    {
        $frwClient = $this->getFrWClientWithAutoRunSettings(true);
        static::assertTrue($frwClient->frwShouldRun());
    }

    public function testShouldRunIsTrueIfFrwHasFailed(): void
    {
        $frwClient = $this->getFrWClientWithAutoRunSettings(true);

        $this->systemConfigService->set(
            'core.frw.failedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );

        $this->systemConfigService->set('core.frw.failureCount', 1);

        static::assertTrue($frwClient->frwShouldRun());
    }

    public function testShouldRunIsFalseIfFrwHasFailedToOften(): void
    {
        $frwClient = $this->getFrWClientWithAutoRunSettings(true);

        $this->systemConfigService->set(
            'core.frw.failedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );

        $this->systemConfigService->set('core.frw.failureCount', 4);

        static::assertFalse($frwClient->frwShouldRun());
    }

    public function testGetLanguagePlugins(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/languagePluginsResponse.json'))
        );

        $plugins = $this->getSystemFwrClient()->getLanguagePlugins('en_GB', new PluginCollection());

        static::assertCount(1, $plugins);

        $languagePackPlugin = $plugins[0];

        static::assertEquals('SwagLanguagePack', $languagePackPlugin->getName());
        static::assertFalse($languagePackPlugin->isInstalled());
        static::assertFalse($languagePackPlugin->isActive());

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals('/swplatform/firstrunwizard/localizations', $lastRequest->getUri()->getPath());
    }

    public function testGetDemoDataPlugins(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/demoDataPluginsResponse.json'))
        );

        $plugins = $this->getSystemFwrClient()->getDemoDataPlugins('en_GB', new PluginCollection());

        static::assertCount(1, $plugins);

        $languagePackPlugin = $plugins[0];

        static::assertEquals('SwagPlatformDemoData', $languagePackPlugin->getName());
        static::assertFalse($languagePackPlugin->isInstalled());
        static::assertFalse($languagePackPlugin->isActive());

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals('/swplatform/firstrunwizard/demodataplugins', $lastRequest->getUri()->getPath());
    }

    public function testGetRecommendationRegions(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/recommendationRegionsResponse.json'))
        );

        $regions = $this->getSystemFwrClient()->getRecommendationRegions('en_GB');

        static::assertCount(1, $regions);

        $regionsAsArray = \json_decode(\json_encode($regions), true);

        static::assertEquals([
            [
                'name' => 'dach',
                'label' => 'Germany / Austria / Switzerland',
                'categories' => [
                    [
                        'name' => 'payment',
                        'label' => 'Payment',
                        'extensions' => [],
                    ], [
                        'name' => 'shipping',
                        'label' => 'Shipping & Fulfillment',
                        'extensions' => [],
                    ],
                ],
                'extensions' => [],
            ],
        ], $regionsAsArray);
    }

    public function testGetRecommendation(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/recommendationResponse.json'))
        );

        $frwClient = $this->getSystemFwrClient();
        $recommendations = $frwClient->getRecommendations(
            'en_GB',
            new PluginCollection(),
            'dach',
            'shipping'
        );

        static::assertCount(1, $recommendations);

        $exampleRecommendation = $recommendations->first();

        static::assertEquals('SendcloudShipping', $exampleRecommendation->getName());

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals('/swplatform/firstrunwizard/plugins', $lastRequest->getUri()->getPath());
        static::assertEquals([
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => 'en_GB',
            'market' => 'dach',
            'region' => 'dach',
            'category' => 'shipping',
        ], Query::parse($lastRequest->getUri()->getQuery()));
    }

    public function testGetLicenseDomains(): void
    {
        $this->systemConfigService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'http://test-shop');

        $storeToken = $this->getStoreTokenFromContext($this->storeContext);

        $this->getRequestHandler()->append(
            new Response(200, [], \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/licenseDomainResponse.json'))
        );

        $domains = $this->getSystemFwrClient()->getLicenseDomains('en_Gb', $this->storeContext);

        static::assertEquals([
            [
                'domain' => 'http://test-shop',
                'verified' => true,
                'edition' => 'Community Edition',
                'active' => true,
                'extensions' => [],
            ],
            [
                'domain' => 'http://new-shop',
                'verified' => false,
                'edition' => 'Community Edition',
                'active' => false,
                'extensions' => [],
            ],
        ], \json_decode(\json_encode($domains), true));

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertEquals('/swplatform/firstrunwizard/shops', $lastRequest->getUri()->getPath());
        static::assertEquals($storeToken, $lastRequest->getHeader('X-Shopware-Token')[0]);
    }

    public function testVerifyLicenseDomain(): void
    {
        $shopDomain = 'http://new-shop';

        $this->systemConfigService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, $shopDomain);
        $verificationResponse = \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/verificationResponse.json');
        $verificationData = \json_decode($verificationResponse, true);

        $licenses = \file_get_contents(__DIR__ . '/../_fixtures/FirstRunWizard/licenseDomainResponse.json');

        $verifiedShops = \json_decode($licenses, true);
        $verifiedShops[1]['verified'] = true;
        $verifiedShops = \json_encode($verifiedShops);

        $this->getRequestHandler()->append(
            new Response(200, [], $licenses),
            new Response(200, [], $verificationResponse),
            new Response(200, []),
            new Response(200, [], $verifiedShops)
        );

        $verifiedShop = $this->getSystemFwrClient()->verifyLicenseDomain(
            $shopDomain,
            'en_GB',
            $this->storeContext,
            true
        );

        $verificationHash = $this->getContainer()->get('shopware.filesystem.public')->read($verificationData['fileName']);
        static::assertEquals($verificationData['content'], $verificationHash);

        static::assertEquals($shopDomain, $verifiedShop->getDomain());
        static::assertTrue($verifiedShop->isActive());
        static::assertTrue($verifiedShop->isVerified());

        static::assertEquals($shopDomain, $this->systemConfigService->get(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN));
        static::assertEquals('Community Edition', $this->systemConfigService->get(StoreService::CONFIG_KEY_STORE_LICENSE_EDITION));
    }

    private function resetFrwState(): void
    {
        $this->systemConfigService->set('core.frw.completedAt', null);
        $this->systemConfigService->set('core.frw.failedAt', null);
        $this->systemConfigService->set('core.frw.failureCount', null);
    }

    private function getFrWClientWithAutoRunSettings(bool $frwAutoRun)
    {
        return new FirstRunWizardClient(
            $this->getContainer()->get(StoreService::class),
            $this->getContainer()->get(SystemConfigService::class),
            $this->getContainer()->get('shopware.filesystem.public'),
            $frwAutoRun,
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('shopware.store_client'),
            $this->getContainer()->get(FrwRequestOptionsProvider::class),
            $this->getContainer()->get(InstanceService::class)
        );
    }

    private function getFwrClientForStateTests(EventDispatcherInterface $mockEventDispatcher): FirstRunWizardClient
    {
        return new FirstRunWizardClient(
            $this->getContainer()->get(StoreService::class),
            $this->getContainer()->get(SystemConfigService::class),
            $this->getContainer()->get('shopware.filesystem.public'),
            true,
            $mockEventDispatcher,
            $this->getContainer()->get('shopware.store_client'),
            $this->getContainer()->get(FrwRequestOptionsProvider::class),
            $this->getContainer()->get(InstanceService::class)
        );
    }

    private function getSystemFwrClient(): FirstRunWizardClient
    {
        return $this->getContainer()->get(FirstRunWizardClient::class);
    }
}
