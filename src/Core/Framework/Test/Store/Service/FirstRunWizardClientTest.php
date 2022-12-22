<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Store\Event\FirstRunWizardStartedEvent;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Services\TrackingEventClient;
use Shopware\Core\Framework\Store\Struct\StorePluginStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class FirstRunWizardClientTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;
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

        $frwClient = $this->getFrwClientForStateTests($eventDispatcher);

        $frwClient->startFrw($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
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

        $frwClient = $this->getFrwClientForStateTests($eventDispatcher);

        $frwClient->startFrw($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
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

        $frwClient = $this->getFrwClientForStateTests($eventDispatcher);

        $frwClient->startFrw($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
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
        $this->getSystemFwrClient()->frwLogin('1', 'shopware', Context::createDefaultContext());
    }

    public function testFrwLogin(): void
    {
        $this->getRequestHandler()->append(new Response(200, [], \json_encode([
            'firstRunWizardUserToken' => [
                'token' => 'updatedToken',
                'expirationDate' => (new \DateTimeImmutable())->format(\DateTime::ATOM),
            ],
        ], \JSON_THROW_ON_ERROR)));

        $this->getSystemFwrClient()->frwLogin('1', 'shopware', $this->storeContext);

        static::assertEquals('updatedToken', $this->getFrwUserTokenFromContext($this->storeContext));

        $lastRequest = $this->getRequestHandler()->getLastRequest();

        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('POST', $lastRequest->getMethod());
        static::assertEquals('/swplatform/firstrunwizard/login', $lastRequest->getUri()->getPath());

        static::assertEquals([
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => 'en-GB',
            'domain' => '',
        ], Query::parse($lastRequest->getUri()->getQuery()));

        static::assertEquals([
            'shopwareId' => '1',
            'password' => 'shopware',
        ], \json_decode($lastRequest->getBody()->getContents(), true));
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
            ], \JSON_THROW_ON_ERROR))
        );

        $this->getSystemFwrClient()->upgradeAccessToken($this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);

        static::assertEquals([
            'shopwareUserId' => $this->getStoreContextSource()->getUserId(),
        ], \json_decode($lastRequest->getBody()->getContents(), true));

        static::assertEquals('this-shop-is-secret', $this->systemConfigService->get('core.store.shopSecret'));
        static::assertEquals('updatedToken', $this->getStoreTokenFromContext($this->storeContext));
        static::assertNull($this->getFrwUserTokenFromContext($this->storeContext));
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

        $frwClient = $this->getFrwClientForStateTests($mockEventDispatcher);
        $frwClient->finishFrw(false, $this->storeContext);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
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

        $frwClient = $this->getFrwClientForStateTests($mockEventDispatcher);

        $frwClient->finishFrw(true, $this->storeContext);
    }

    public function testShouldRunIsAlwaysFalseIfFrwAutoRunIsFalse(): void
    {
        $frwClient = $this->getFrwClientWithAutoRunSettings(false);
        static::assertFalse($frwClient->frwShouldRun());
    }

    public function testShouldRunIsFalseIfFrwIsCompleted(): void
    {
        $frwClient = $this->getFrwClientWithAutoRunSettings(true);

        $this->systemConfigService->set(
            'core.frw.completedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );

        static::assertFalse($frwClient->frwShouldRun());
    }

    public function testShouldRunIsTrueIfFrwDidNotStartYet(): void
    {
        $frwClient = $this->getFrwClientWithAutoRunSettings(true);
        static::assertTrue($frwClient->frwShouldRun());
    }

    public function testShouldRunIsTrueIfFrwHasFailed(): void
    {
        $frwClient = $this->getFrwClientWithAutoRunSettings(true);

        $this->systemConfigService->set(
            'core.frw.failedAt',
            (new \DateTimeImmutable())->format(\DateTime::ATOM)
        );

        $this->systemConfigService->set('core.frw.failureCount', 1);

        static::assertTrue($frwClient->frwShouldRun());
    }

    public function testShouldRunIsFalseIfFrwHasFailedToOften(): void
    {
        $frwClient = $this->getFrwClientWithAutoRunSettings(true);

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
            new Response(200, [], $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/languagePluginsResponse.json'))
        );

        $plugins = $this->getSystemFwrClient()->getLanguagePlugins(new PluginCollection(), $this->storeContext);

        static::assertCount(1, $plugins);

        $languagePackPlugin = $plugins[0];

        static::assertEquals('SwagLanguagePack', $languagePackPlugin->getName());
        static::assertFalse($languagePackPlugin->isInstalled());
        static::assertFalse($languagePackPlugin->isActive());

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('/swplatform/firstrunwizard/localizations', $lastRequest->getUri()->getPath());
    }

    public function testGetDemoDataPlugins(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/demoDataPluginsResponse.json'))
        );

        $plugins = $this->getSystemFwrClient()->getDemoDataPlugins(new PluginCollection(), $this->storeContext);

        static::assertCount(1, $plugins);

        $languagePackPlugin = $plugins[0];

        static::assertEquals('SwagPlatformDemoData', $languagePackPlugin->getName());
        static::assertFalse($languagePackPlugin->isInstalled());
        static::assertFalse($languagePackPlugin->isActive());

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('/swplatform/firstrunwizard/demodataplugins', $lastRequest->getUri()->getPath());
    }

    public function testGetRecommendationRegions(): void
    {
        $this->getRequestHandler()->append(
            new Response(200, [], $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/recommendationRegionsResponse.json'))
        );

        $regions = $this->getSystemFwrClient()->getRecommendationRegions($this->storeContext);

        static::assertCount(1, $regions);

        $regionsAsArray = \json_decode(\json_encode($regions, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

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
            new Response(200, [], $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/recommendationResponse.json'))
        );

        $frwClient = $this->getSystemFwrClient();
        $recommendations = $frwClient->getRecommendations(
            new PluginCollection(),
            'dach',
            'shipping',
            $this->storeContext
        );

        static::assertCount(1, $recommendations);

        $exampleRecommendation = $recommendations->first();
        static::assertInstanceOf(StorePluginStruct::class, $exampleRecommendation);

        static::assertEquals('SendcloudShipping', $exampleRecommendation->getName());

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('/swplatform/firstrunwizard/plugins', $lastRequest->getUri()->getPath());
        static::assertEquals([
            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
            'language' => 'en-GB',
            'market' => 'dach',
            'region' => 'dach',
            'category' => 'shipping',
            'domain' => '',
        ], Query::parse($lastRequest->getUri()->getQuery()));
    }

    public function testGetLicenseDomains(): void
    {
        $this->systemConfigService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'http://test-shop');

        $this->setFrwUserToken($this->storeContext, 'frw-user-token');
        $frwUserToken = $this->getFrwUserTokenFromContext($this->storeContext);

        $this->getRequestHandler()->append(
            new Response(200, [], $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/licenseDomainResponse.json'))
        );

        $domains = $this->getSystemFwrClient()->getLicenseDomains($this->storeContext);

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
        ], \json_decode(\json_encode($domains, \JSON_THROW_ON_ERROR), true));

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $lastRequest);
        static::assertEquals('/swplatform/firstrunwizard/shops', $lastRequest->getUri()->getPath());
        static::assertEquals($frwUserToken, $lastRequest->getHeader('X-Shopware-Token')[0]);
    }

    public function testVerifyLicenseDomain(): void
    {
        $shopDomain = 'http://new-shop';

        $this->systemConfigService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, $shopDomain);
        $verificationResponse = $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/verificationResponse.json');
        $verificationData = \json_decode($verificationResponse, true);

        $licenses = $this->getFileContents(__DIR__ . '/../_fixtures/FirstRunWizard/licenseDomainResponse.json');

        $verifiedShops = \json_decode($licenses, true, 512, \JSON_THROW_ON_ERROR);
        $verifiedShops[1]['verified'] = true;
        $verifiedShops = \json_encode($verifiedShops, \JSON_THROW_ON_ERROR);

        static::assertIsString($verifiedShops);

        $this->getRequestHandler()->append(
            new Response(200, [], $licenses),
            new Response(200, [], $verificationResponse),
            new Response(200, []),
            new Response(200, [], $verifiedShops)
        );

        $verifiedShop = $this->getSystemFwrClient()->verifyLicenseDomain(
            $shopDomain,
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

    private function getFrwClientWithAutoRunSettings(bool $frwAutoRun): FirstRunWizardClient
    {
        return new FirstRunWizardClient(
            $this->getContainer()->get(StoreService::class),
            $this->getContainer()->get(SystemConfigService::class),
            $this->getContainer()->get('shopware.filesystem.public'),
            $frwAutoRun,
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('shopware.store_client'),
            $this->getContainer()->get(FrwRequestOptionsProvider::class),
            $this->getContainer()->get(InstanceService::class),
            $this->getContainer()->get('user_config.repository'),
            $this->getContainer()->get(TrackingEventClient::class)
        );
    }

    private function getFrwClientForStateTests(EventDispatcherInterface $mockEventDispatcher): FirstRunWizardClient
    {
        return new FirstRunWizardClient(
            $this->getContainer()->get(StoreService::class),
            $this->getContainer()->get(SystemConfigService::class),
            $this->getContainer()->get('shopware.filesystem.public'),
            true,
            $mockEventDispatcher,
            $this->getContainer()->get('shopware.store_client'),
            $this->getContainer()->get(FrwRequestOptionsProvider::class),
            $this->getContainer()->get(InstanceService::class),
            $this->getContainer()->get('user_config.repository'),
            $this->getContainer()->get(TrackingEventClient::class)
        );
    }

    private function getSystemFwrClient(): FirstRunWizardClient
    {
        return $this->getContainer()->get(FirstRunWizardClient::class);
    }

    private function getStoreContextSource(): AdminApiSource
    {
        $contextSource = $this->storeContext->getSource();

        static::assertInstanceOf(AdminApiSource::class, $contextSource);

        return $contextSource;
    }

    private function getFileContents(string $fileName): string
    {
        $fileContents = \file_get_contents($fileName);

        static::assertIsString($fileContents);

        return $fileContents;
    }
}
