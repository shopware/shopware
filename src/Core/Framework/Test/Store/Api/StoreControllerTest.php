<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Api\StoreController;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class StoreControllerTest extends TestCase
{
    use KernelTestBehaviour;
    use IntegrationTestBehaviour;

    private Context $defaultContext;

    private EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->defaultContext = Context::createDefaultContext();
        $this->userRepository = $this->getContainer()->get('user.repository');
    }

    /**
     * This is a regression test for NEXT-12957. It ensures, that the downloadPlugin method of the StoreController does
     * not dispatch a call to the PluginLifecycleService::updatePlugin method.
     *
     * @see https://issues.shopware.com/issues/NEXT-12957
     */
    public function testDownloadPluginUpdateBehaviour(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $pluginLifecycleService = $this->getPluginLifecycleServiceMock();
        $pluginLifecycleService->expects(static::never())->method('updatePlugin');

        $storeController = $this->getStoreController();

        $storeController->downloadPlugin(
            new QueryDataBag([
                'unauthenticated' => true,
                'language' => 'not-null',
                'pluginName' => 'not-null',
            ]),
            Context::createDefaultContext()
        );
    }

    public function testCheckLoginWithoutStoreToken(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertNull($response['userInfo']);
    }

    public function testPingStoreApiAvailable(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('ping');

        $storeController = $this->getStoreController($storeClientMock);

        $response = $storeController->pingStoreAPI();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testPingStoreApiNotAvailable(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('ping')
            ->willThrowException($this->createMock(ConnectException::class));

        $storeController = $this->getStoreController($storeClientMock);

        static::expectException(StoreNotAvailableException::class);
        $storeController->pingStoreAPI();
    }

    public function testPingStoreApiIsDeprecated(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeController = $this->getStoreController($storeClientMock);

        static::expectException(\RuntimeException::class);
        $storeController->pingStoreAPI();
    }

    public function testLoginWithCorrectCredentials(): void
    {
        $request = new Request([], [
            'shopwareId' => 'j.doe@shopware.com',
            'password' => 'v3rys3cr3t',
        ]);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('loginWithShopwareId')
            ->with('j.doe@shopware.com', 'v3rys3cr3t');

        $storeController = $this->getStoreController($storeClientMock);

        $response = $storeController->login($request, $context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(200, $response->getStatusCode());
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $request = new Request([], [
            'shopwareId' => 'j.doe@shopware.com',
            'password' => 'v3rys3cr3t',
        ]);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $clientExceptionMock = $this->createMock(ClientException::class);
        $clientExceptionMock->method('getResponse')
            ->willReturn(new Response());

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::once())
            ->method('loginWithShopwareId')
            ->willThrowException($clientExceptionMock);

        $storeController = $this->getStoreController($storeClientMock);

        static::expectException(StoreApiException::class);
        $storeController->login($request, $context);
    }

    public function testLoginWithInvalidCredentialsInput(): void
    {
        $request = new Request([], [
            'shopwareId' => null,
            'password' => null,
        ]);

        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $context = new Context(new AdminApiSource($adminUser->getId()));

        $storeClientMock = $this->createMock(StoreClient::class);
        $storeClientMock->expects(static::never())
            ->method('loginWithShopwareId');

        $storeController = $this->getStoreController($storeClientMock);

        static::expectException(StoreInvalidCredentialsException::class);
        $storeController->login($request, $context);
    }

    public function testCheckLoginWithStoreToken(): void
    {
        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $this->userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 'store-token',
        ]], $this->defaultContext);

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($response['userInfo'], [
            'name' => 'John Doe',
            'email' => 'john.doe@shopware.com',
        ]);
    }

    public function testCheckLoginWithMultipleStoreTokens(): void
    {
        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $this->userRepository->update([[
            'id' => $adminUser->getId(),
            'storeToken' => 'store-token',
            'firstName' => 'John',
        ]], $this->defaultContext);

        $this->userRepository->create([[
            'id' => Uuid::randomHex(),
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'storeToken' => 'store-token-two',
            'localeId' => $adminUser->getLocaleId(),
            'username' => 'admin-two',
            'password' => 's3cr3t',
            'email' => 'jane.doe@shopware.com',
        ]], $this->defaultContext);

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($response['userInfo'], [
            'name' => 'John Doe',
            'email' => 'john.doe@shopware.com',
        ]);
    }

    private function getStoreController(
        ?StoreClient $storeClient = null,
        ?EntityRepository $pluginRepo = null,
        ?PluginManagementService $pluginManagementService = null
    ): StoreController {
        return new StoreController(
            $storeClient ?? $this->getStoreClientMock(),
            $pluginRepo ?? $this->getPluginRepositoryMock(),
            $pluginManagementService ?? $this->getPluginManagementServiceMock(),
            $this->userRepository,
            $this->getContainer()->get(AbstractExtensionDataProvider::class)
        );
    }

    /**
     * @return StoreClient|MockObject
     */
    private function getStoreClientMock(): StoreClient
    {
        $storeClient = $this->getMockBuilder(StoreClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDownloadDataForPlugin', 'userInfo'])
            ->getMock();

        $storeClient->method('getDownloadDataForPlugin')
            ->willReturn($this->getPluginDownloadDataStub());

        $storeClient->method('userInfo')
            ->willReturn([
                'name' => 'John Doe',
                'email' => 'john.doe@shopware.com',
            ]);

        return $storeClient;
    }

    /**
     * @return EntityRepository|MockObject
     */
    private function getPluginRepositoryMock(): EntityRepository
    {
        $pluginRepository = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search'])
            ->getMock();

        $pluginRepository->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'plugin',
                    1,
                    new EntityCollection([
                        $this->getPluginStub(),
                    ]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        return $pluginRepository;
    }

    /**
     * @return PluginManagementService|MockObject
     */
    private function getPluginManagementServiceMock(): PluginManagementService
    {
        $pluginManagementService = $this->getMockBuilder(PluginManagementService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['downloadStorePlugin'])
            ->getMock();

        return $pluginManagementService;
    }

    /**
     * @return PluginLifecycleService|MockObject
     */
    private function getPluginLifecycleServiceMock(): PluginLifecycleService
    {
        return $this->getMockBuilder(PluginLifecycleService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['updatePlugin'])
            ->getMock();
    }

    private function getPluginStub(): PluginEntity
    {
        $plugin = new PluginEntity();

        $plugin->setId('0f4384bc2d884f519bd3627c3d91d539');
        $plugin->setUpgradeVersion('not-null');
        $plugin->setManagedByComposer(false);

        return $plugin;
    }

    private function getPluginDownloadDataStub(): PluginDownloadDataStruct
    {
        return (new PluginDownloadDataStruct())
            ->assign([
                'location' => 'not-null',
            ]);
    }
}
