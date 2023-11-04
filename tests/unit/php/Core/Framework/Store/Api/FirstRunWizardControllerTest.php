<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Api\FirstRunWizardController;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Store\Struct\PluginRecommendationCollection;
use Shopware\Core\Framework\Store\Struct\PluginRegionCollection;
use Shopware\Core\Framework\Store\Struct\StorePluginStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Api\FirstRunWizardController
 */
class FirstRunWizardControllerTest extends TestCase
{
    private MockObject $firstRunWizardService;

    private MockObject $pluginRepository;

    private FirstRunWizardController $controller;

    protected function setUp(): void
    {
        $this->firstRunWizardService = $this->createMock(FirstRunWizardService::class);
        $this->pluginRepository = $this->createMock(EntityRepository::class);
        $this->controller = new FirstRunWizardController($this->firstRunWizardService, $this->pluginRepository);
    }

    public function testStartFrw(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('startFrw');

        $response = $this->controller->frwStart($this->createContext());

        static::assertSame(SymfonyResponse::HTTP_OK, $response->getStatusCode());
    }

    public function testTryingToStartFrwFails(): void
    {
        $exceptionMessage = 'frwService::frwStart failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('startFrw')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->frwStart($this->createContext());
    }

    public function testGetLanguagePluginList(): void
    {
        $context = $this->createContext();
        $plugin1Name = 'SwagTest1';
        $this->pluginRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->createPluginSearchResult($context, [
                ['name' => $plugin1Name],
            ]));

        $this->firstRunWizardService->expects(static::once())
            ->method('getLanguagePlugins')
            ->willReturn([
                (new StorePluginStruct())->assign(['name' => $plugin1Name]),
            ]);

        $response = $this->controller->getLanguagePluginList($context);
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('items', $responseData);
        static::assertArrayHasKey('total', $responseData);
    }

    public function testTryingToGetLanguagePluginListFails(): void
    {
        $context = $this->createContext();
        $this->pluginRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->createPluginSearchResult($context, [
                ['name' => 'SwagTest1'],
            ]));

        $exceptionMessage = 'frwService::getLanguagePlugins failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('getLanguagePlugins')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->getLanguagePluginList($context);
    }

    public function testGetDemoDataPluginList(): void
    {
        $context = $this->createContext();
        $plugin1Name = 'SwagTest1';
        $this->pluginRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->createPluginSearchResult($context, [
                ['name' => $plugin1Name],
            ]));

        $this->firstRunWizardService->expects(static::once())
            ->method('getDemoDataPlugins')
            ->willReturn([
                (new StorePluginStruct())->assign(['name' => $plugin1Name]),
            ]);

        $response = $this->controller->getDemoDataPluginList($context);
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('items', $responseData);
        static::assertArrayHasKey('total', $responseData);
    }

    public function testTryingToGetDemoDataPluginListFails(): void
    {
        $context = $this->createContext();
        $this->pluginRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->createPluginSearchResult($context, [
                ['name' => 'SwagTest1'],
            ]));

        $exceptionMessage = 'frwService::getDemoDataPlugins failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('getDemoDataPlugins')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->getDemoDataPluginList($context);
    }

    public function testGetRecommendationRegions(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('getRecommendationRegions')
            ->willReturn(new PluginRegionCollection([]));

        $response = $this->controller->getRecommendationRegions($this->createContext());
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('items', $responseData);
        static::assertArrayHasKey('total', $responseData);
    }

    public function testTryingToGetRecommendationRegionsFails(): void
    {
        $exceptionMessage = 'frwService::getRecommendationRegions failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('getRecommendationRegions')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->getRecommendationRegions($this->createContext());
    }

    public function testGetRecommendations(): void
    {
        $context = $this->createContext();
        $plugin1Name = 'SwagTest1';
        $this->pluginRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->createPluginSearchResult($context, [
                ['name' => $plugin1Name],
            ]));

        $this->firstRunWizardService->expects(static::once())
            ->method('getRecommendations')
            ->willReturn(new PluginRecommendationCollection([
                (new StorePluginStruct())->assign(['name' => $plugin1Name]),
            ]));

        $response = $this->controller->getRecommendations(new SymfonyRequest(), $context);
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('items', $responseData);
        static::assertArrayHasKey('total', $responseData);
    }

    public function testTryingToGetRecommendationsFails(): void
    {
        $context = $this->createContext();
        $this->pluginRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->createPluginSearchResult($context, [
                ['name' => 'SwagTest1'],
            ]));

        $exceptionMessage = 'frwService::getRecommendations failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('getRecommendations')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->getRecommendations(new SymfonyRequest(), $context);
    }

    public function testLoginWithFrw(): void
    {
        $requestDataBag = new RequestDataBag([
            'shopwareId' => 'testShopwareId',
            'password' => 'testPassword',
        ]);

        $this->firstRunWizardService->expects(static::once())
            ->method('frwLogin');

        $response = $this->controller->frwLogin($requestDataBag, $this->createContext());

        static::assertSame(SymfonyResponse::HTTP_OK, $response->getStatusCode());
    }

    public function testTryToLoginWithFrwWithoutShopwareId(): void
    {
        $requestDataBag = new RequestDataBag([
            'password' => 'testPassword',
        ]);

        $this->firstRunWizardService->expects(static::never())
            ->method('frwLogin');
        static::expectException(StoreInvalidCredentialsException::class);

        $this->controller->frwLogin($requestDataBag, $this->createContext());
    }

    public function testTryToLoginWithFrwWithoutPassword(): void
    {
        $requestDataBag = new RequestDataBag([
            'shopwareId' => 'testShopwareId',
        ]);

        $this->firstRunWizardService->expects(static::never())
            ->method('frwLogin');
        static::expectException(StoreInvalidCredentialsException::class);

        $this->controller->frwLogin($requestDataBag, $this->createContext());
    }

    public function testTryingToLoginWithFrwFails(): void
    {
        $requestDataBag = new RequestDataBag([
            'shopwareId' => 'testShopwareId',
            'password' => 'testPassword',
        ]);

        $exceptionMessage = 'frwService::frwLogin failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('frwLogin')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->frwLogin($requestDataBag, $this->createContext());
    }

    public function testGetDomainList(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('getLicenseDomains');

        $response = $this->controller->getDomainList($this->createContext());
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('items', $responseData);
        static::assertArrayHasKey('total', $responseData);
    }

    public function testTryingToGetDomainListFails(): void
    {
        $exceptionMessage = 'frwService::getLicenseDomains failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('getLicenseDomains')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->getDomainList($this->createContext());
    }

    public function testVerifyDomain(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('verifyLicenseDomain');

        $response = $this->controller->verifyDomain(new QueryDataBag([
            'domain' => 'test-domain.com',
            'testEnvironment' => 'false',
        ]), $this->createContext());
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('data', $responseData);
    }

    public function testVerifyDomainWithoutDomain(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('verifyLicenseDomain');

        $response = $this->controller->verifyDomain(
            new QueryDataBag(['testEnvironment' => 'false']),
            $this->createContext()
        );
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('data', $responseData);
    }

    public function testVerifyDomainWithoutTestEnvironment(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('verifyLicenseDomain');

        $response = $this->controller->verifyDomain(
            new QueryDataBag(['domain' => 'test-domain.com']),
            $this->createContext()
        );
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('data', $responseData);
    }

    public function testVerifyDomainInTestEnvironment(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('verifyLicenseDomain');

        $response = $this->controller->verifyDomain(new QueryDataBag([
            'domain' => 'test-domain.com',
            'testEnvironment' => 'true',
        ]), $this->createContext());
        $responseData = $this->decodeJsonResponse($response);

        static::assertArrayHasKey('data', $responseData);
    }

    public function testTryingToVerifyDomainFails(): void
    {
        $exceptionMessage = 'frwService::getLicenseDomains failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('verifyLicenseDomain')
            ->willThrowException($this->createClientException($exceptionMessage));

        static::expectException(StoreApiException::class);
        static::expectExceptionMessage($exceptionMessage);
        $this->controller->verifyDomain(new QueryDataBag([
            'domain' => 'test-domain.com',
            'testEnvironment' => 'false',
        ]), $this->createContext());
    }

    public function testFinishFrw(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('finishFrw');
        $this->firstRunWizardService->expects(static::once())
            ->method('upgradeAccessToken');

        $response = $this->controller->frwFinish(new QueryDataBag(['failed' => 'true']), $this->createContext());

        static::assertEquals(SymfonyResponse::HTTP_OK, $response->getStatusCode());
    }

    public function testFinishFrwWithoutFailedParam(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('finishFrw');
        $this->firstRunWizardService->expects(static::once())
            ->method('upgradeAccessToken');

        $response = $this->controller->frwFinish(new QueryDataBag([]), $this->createContext());

        static::assertEquals(SymfonyResponse::HTTP_OK, $response->getStatusCode());
    }

    public function testFinishFrwButUpgradingAccessTokenFails(): void
    {
        $this->firstRunWizardService->expects(static::once())
            ->method('finishFrw');
        $exceptionMessage = 'frwService::upgradeAccessToken failed';
        $this->firstRunWizardService->expects(static::once())
            ->method('upgradeAccessToken')
            ->willThrowException(new \Exception($exceptionMessage));

        $response = $this->controller->frwFinish(new QueryDataBag(['failed' => 'false']), $this->createContext());

        static::assertEquals(SymfonyResponse::HTTP_OK, $response->getStatusCode());
    }

    private function decodeJsonResponse(JsonResponse $response): mixed
    {
        $responseContent = $response->getContent();
        static::assertNotFalse($responseContent);

        return json_decode($responseContent, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function createContext(): Context
    {
        return Context::createDefaultContext();
    }

    private function createClientException(string $message): ClientException
    {
        return new ClientException($message, $this->createMock(GuzzleRequest::class), new Response(400));
    }

    /**
     * @param array<string, mixed>[] $data
     */
    private function createPluginCollection(array $data): PluginCollection
    {
        $collection = new PluginCollection();
        for ($i = 0; $i < \count($data); ++$i) {
            if (!\array_key_exists('id', $data[$i])) {
                $data[$i]['id'] = Uuid::randomHex();
            }
            $collection->add((new PluginEntity())->assign($data[$i]));
        }

        return $collection;
    }

    /**
     * @param array<string, mixed>[] $pluginData
     */
    private function createPluginSearchResult(Context $context, array $pluginData): EntitySearchResult
    {
        return new EntitySearchResult(
            PluginEntity::class,
            \count($pluginData),
            $this->createPluginCollection($pluginData),
            null,
            new Criteria(),
            $context
        );
    }
}
