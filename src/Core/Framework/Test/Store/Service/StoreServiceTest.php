<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;

class StoreServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private StoreService $storeService;

    public function setUp(): void
    {
        $this->storeService = $this->getContainer()->get(StoreService::class);
    }

    public function testGetDefaultQueryParametersIncludesLicenseDomainIfSet(): void
    {
        $this->setLicenseDomain('test-shop');

        $queryParameters = $this->storeService->getDefaultQueryParameters('en_GB');

        static::assertEquals([
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => 'en_GB',
            'domain' => 'test-shop',
        ], $queryParameters);
    }

    public function testGetDefaultQueryParametersRemovesEmptyQueries(): void
    {
        $queries = $this->storeService->getDefaultQueryParameters('en_GB', false);

        static::assertArrayHasKey('language', $queries);
        static::assertEquals('en_GB', $queries['language']);

        static::assertArrayHasKey('domain', $queries);
        static::assertEquals('', $queries['domain']);

        static::assertArrayHasKey('shopwareVersion', $queries);
        static::assertEquals($this->getShopwareVersion(), $queries['shopwareVersion']);
    }

    public function testFireTrackingEventReturnsOnNonExistingInstanceId(): void
    {
        $instanceService = new InstanceService(Kernel::SHOPWARE_FALLBACK_VERSION, null);

        $storeService = new StoreService(
            $this->getContainer()->get('shopware.store_client'),
            $this->getContainer()->get('user.repository'),
            $instanceService,
            $this->getContainer()->get(StoreRequestOptionsProvider::class),
        );

        $this->getRequestHandler()->reset();
        $storeService->fireTrackingEvent('Example event name');

        // an exception would be thrown if a request was made
    }

    public function testFireTrackingEventReturns(): void
    {
        $instanceService = $this->getContainer()->get(InstanceService::class);
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200));

        $this->storeService->fireTrackingEvent('Example event name', [
            'someAdditionalData' => 'xy',
        ]);

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
        $instanceService = $this->getContainer()->get(InstanceService::class);

        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new \Exception());
        $this->storeService->fireTrackingEvent('Example event name');

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

    public function testGetLanguageFromContextReturnsEnglishIfContextIsNotAdminApiContext(): void
    {
        $language = $this->storeService->getLanguageByContext(Context::createDefaultContext());

        static::assertEquals('en-GB', $language);
    }

    public function testGetLanguageFromContextReturnsEnglishForIntegrations(): void
    {
        $context = new Context(new AdminApiSource(null, Uuid::randomHex()));

        $language = $this->storeService->getLanguageByContext($context);

        static::assertEquals('en-GB', $language);
    }

    public function testGetLanguageFromContextReturnsLocaleFromUser(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $language = $this->storeService->getLanguageByContext($adminStoreContext);

        $criteria = new Criteria([$adminStoreContext->getSource()->getUserId()]);
        $criteria->addAssociation('locale');

        $storeUser = $this->getUserRepository()->search($criteria, $adminStoreContext)->first();

        static::assertEquals($storeUser->getLocale()->getCode(), $language);
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

        $criteria = new Criteria([$adminStoreContext->getSource()->getuserId()]);

        $updatedUser = $this->getUserRepository()->search($criteria, $adminStoreContext)->first();

        static::assertEquals('updated-store-token', $updatedUser->getStoreToken());
    }
}
