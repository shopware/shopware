<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Services\TrackingEventClient;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;

/**
 * @internal
 */
class StoreServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private StoreService $storeService;

    public function setUp(): void
    {
        $this->storeService = $this->getContainer()->get(StoreService::class);
    }

    public function testFireTrackingEventReturnsOnNonExistingInstanceId(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $storeService = new StoreService(
            $this->getContainer()->get('user.repository'),
            new TrackingEventClient(
                $this->getContainer()->get('shopware.store_client'),
                new InstanceService(Kernel::SHOPWARE_FALLBACK_VERSION, null)
            ),
        );

        $this->getRequestHandler()->reset();
        $storeService->fireTrackingEvent('Example event name');

        // an exception would be thrown if a request was made
    }

    public function testFireTrackingEventReturns(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $instanceService = $this->getContainer()->get(InstanceService::class);
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200));

        $this->storeService->fireTrackingEvent('Example event name', [
            'someAdditionalData' => 'xy',
        ]);

        /** @var RequestInterface $lastRequest */
        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => $instanceService->getInstanceId(),
                'additionalData' => [
                    'shopwareVersion' => $instanceService->getShopwareVersion(),
                    'someAdditionalData' => 'xy',
                ],
                'event' => 'Example event name',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true)
        );
    }

    public function testFireTrackingEventReturnsOnThrownException(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $instanceService = $this->getContainer()->get(InstanceService::class);

        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new \Exception());
        $this->storeService->fireTrackingEvent('Example event name');

        /** @var RequestInterface $lastRequest */
        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals('/swplatform/tracking/events', $lastRequest->getUri()->getPath());
        static::assertEquals(
            [
                'instanceId' => $instanceService->getInstanceId(),
                'additionalData' => [
                    'shopwareVersion' => $instanceService->getShopwareVersion(),
                ],
                'event' => 'Example event name',
            ],
            \json_decode($lastRequest->getBody()->getContents(), true)
        );
    }

    public function testFireTrackingEventIsDeprecated(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);

        static::expectException(\Exception::class);
        static::expectDeprecationMessage('Method "Shopware\Core\Framework\Store\Services\StoreService::Shopware\Core\Framework\Store\Services\StoreService::fireTrackingEvent()" is deprecated and will be removed in v6.5.0.0. Use "TrackingEventClient::fireTrackingEvent()" instead.');
        $this->storeService->fireTrackingEvent('Example event name');
    }

    public function testGetLanguageFromContextReturnsEnglishIfContextIsNotAdminApiContext(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $language = $this->storeService->getLanguageByContext(Context::createDefaultContext());

        static::assertEquals('en-GB', $language);
    }

    public function testGetLanguageFromContextReturnsEnglishForIntegrations(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $context = new Context(new AdminApiSource(null, Uuid::randomHex()));

        $language = $this->storeService->getLanguageByContext($context);

        static::assertEquals('en-GB', $language);
    }

    public function testGetLanguageFromContextReturnsLocaleFromUser(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $adminStoreContext = $this->createAdminStoreContext();

        $language = $this->storeService->getLanguageByContext($adminStoreContext);

        /** @var AdminApiSource $adminSource */
        $adminSource = $adminStoreContext->getSource();
        /** @var string $userId */
        $userId = $adminSource->getUserId();
        $criteria = new Criteria([$userId]);
        $criteria->addAssociation('locale');

        $storeUser = $this->getUserRepository()->search($criteria, $adminStoreContext)->first();

        static::assertEquals($storeUser->getLocale()->getCode(), $language);
    }

    public function testGetLanguageFromContextIsDeprecated(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);

        static::expectException(\Exception::class);
        static::expectDeprecationMessage('Method "Shopware\Core\Framework\Store\Services\StoreService::Shopware\Core\Framework\Store\Services\StoreService::getLanguageByContext()" is deprecated and will be removed in v6.5.0.0. Use "LocaleProvider::getLocaleFromContext()" instead.');
        $this->storeService->getLanguageByContext(Context::createDefaultContext());
    }

    public function testUpdateStoreToken(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $newToken = 'updated-store-token';
        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->setShopUserToken((new ShopUserTokenStruct())->assign(['token' => $newToken]));

        $this->storeService->updateStoreToken(
            $adminStoreContext,
            $accessTokenStruct
        );

        /** @var AdminApiSource $adminSource */
        $adminSource = $adminStoreContext->getSource();
        /** @var string $userId */
        $userId = $adminSource->getUserId();
        $criteria = new Criteria([$userId]);

        $updatedUser = $this->getUserRepository()->search($criteria, $adminStoreContext)->first();

        static::assertEquals('updated-store-token', $updatedUser->getStoreToken());
    }
}
