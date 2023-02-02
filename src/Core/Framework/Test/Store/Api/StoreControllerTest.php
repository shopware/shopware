<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Api\StoreController;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function testCheckLoginWithoutStoreToken(): void
    {
        /** @var UserEntity $adminUser */
        $adminUser = $this->userRepository->search(new Criteria(), $this->defaultContext)->first();

        $storeController = $this->getStoreController();
        $context = new Context(new AdminApiSource($adminUser->getId()));

        $response = $storeController->checkLogin($context)->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        static::assertNull($response['userInfo']);
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
            'password' => 's3cr3t12345',
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
    ): StoreController {
        return new StoreController(
            $storeClient ?? $this->getStoreClientMock(),
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

    private function getPluginDownloadDataStub(): PluginDownloadDataStruct
    {
        return (new PluginDownloadDataStruct())
            ->assign([
                'location' => 'not-null',
            ]);
    }
}
