<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Api\ShopIdController;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeStrategyNotFoundException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\FingerprintMatch;
use Shopware\Core\Framework\App\ShopId\FingerprintMismatch;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ShopIdController::class)]
class ShopIdControllerTest extends TestCase
{
    private ShopIdController $controller;

    private Resolver&MockObject $shopIdChangeResolver;

    private ShopIdProvider&MockObject $shopIdProvider;

    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->shopIdChangeResolver = $this->createMock(Resolver::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->appRepository = new StaticEntityRepository([]);
        $this->controller = new ShopIdController($this->shopIdChangeResolver, $this->shopIdProvider, $this->appRepository);
        $this->context = Context::createDefaultContext(new AdminApiSource(null));
    }

    public function testGetAvailableStrategies(): void
    {
        $this->shopIdChangeResolver->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn($expectedStrategies = [
                ['strategy1', 'description1'],
                ['strategy2', 'description2'],
            ]);

        $response = $this->controller->getAvailableStrategies();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = $response->getContent();
        static::assertIsString($body);

        $strategies = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($strategies, $expectedStrategies);
    }

    public function testChangesShopIdUsingTheProvidedStrategy(): void
    {
        $request = new Request(request: ['strategy' => 'testStrategy']);

        $this->shopIdChangeResolver->expects($this->once())
            ->method('resolve')
            ->with($request->request->get('strategy'), $this->context);

        $response = $this->controller->changeShopId($request, $this->context);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testFailsIfResolverThrowsWhenChangingShopId(): void
    {
        $request = new Request(request: ['strategy' => 'testStrategy']);

        $this->shopIdChangeResolver->expects($this->once())
            ->method('resolve')
            ->with($request->request->get('strategy'), $this->context)
            ->willThrowException(AppException::shopIdChangeResolveStrategyNotFound('testStrategy'));

        static::expectExceptionObject(new ShopIdChangeStrategyNotFoundException('testStrategy'));
        $this->controller->changeShopId($request, $this->context);
    }

    public function testFailsIfNoStrategyIsProvided(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $request = new Request();

        $this->shopIdChangeResolver->expects($this->never())
            ->method('resolve');

        static::expectExceptionObject(AppException::missingRequestParameter('strategy'));
        $this->controller->changeShopId($request, $context);
    }

    public function testGetFingerprintsWhenShopIdChangeSuggested(): void
    {
        $shopId = ShopId::v2('123456789');
        $fingerprints = new FingerprintComparisonResult([
            'fingerprint1' => new FingerprintMatch('fingerprint1', 'value1', 25),
        ], [
            'fingerprint2' => new FingerprintMismatch('fingerprint2', 'value2', 'expectedValue2', 50),
            'fingerprint3' => new FingerprintMismatch('fingerprint3', 'value3', 'expectedValue3', 75),
        ], 75);

        $this->shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willThrowException(AppException::shopIdChangeSuggested($shopId, $fingerprints));

        $this->appRepository->addSearch(new AppCollection([
            (new AppEntity())->assign(['id' => 'app-1', 'translated' => ['label' => 'App 1']]),
            (new AppEntity())->assign(['id' => 'app-2', 'translated' => ['label' => 'App 2']]),
        ]));

        $response = $this->controller->checkShopId($this->context);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $body = $response->getContent();
        static::assertIsString($body);

        $comparisonResult = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        static::assertEquals([
            'fingerprints' => [
                'matchingFingerprints' => [
                    'fingerprint1' => ['identifier' => 'fingerprint1', 'storedStamp' => 'value1', 'score' => 25],
                ],
                'mismatchingFingerprints' => [
                    'fingerprint2' => ['identifier' => 'fingerprint2', 'storedStamp' => 'value2', 'expectedStamp' => 'expectedValue2', 'score' => 50],
                    'fingerprint3' => ['identifier' => 'fingerprint3', 'storedStamp' => 'value3', 'expectedStamp' => 'expectedValue3', 'score' => 75],
                ],
                'score' => 125,
                'threshold' => 75,
            ],
            'apps' => ['App 1', 'App 2'],
        ], $comparisonResult);
    }

    public function testGetFingerprintsWhenShopIdChangeNotSuggested(): void
    {
        $shopId = ShopId::v2('123456789');

        $this->shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId->id);

        $response = $this->controller->checkShopId($this->context);

        static::assertEmpty($response->getContent());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
