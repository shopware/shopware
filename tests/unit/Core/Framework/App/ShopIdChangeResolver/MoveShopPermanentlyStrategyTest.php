<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MoveShopPermanentlyStrategy::class)]
class MoveShopPermanentlyStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            static::createStub(AppManager::class),
            static::createStub(ShopIdProvider::class),
            new NullLogger(),
            static::createStub(AppUrlVerifier::class),
            'https://new.example.com',
        );

        static::assertSame(MoveShopPermanentlyStrategy::STRATEGY_NAME, $strategy->getName());
        static::assertNotSame('', $strategy->getDescription());
    }

    public function testNoResolutionNeededWhenShopIdIsNotSuggestedToChange(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('reset');
        $shopIdProvider->expects($this->once())->method('getShopId')->willReturn(ShopId::v2('shop-id'));
        $shopIdProvider->expects($this->never())->method('regenerateAndSetShopId');

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())->method('refreshRegistration');

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->never())->method('forceVerify');

        $appRepository = new StaticEntityRepository([]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new NullLogger(),
            $verifier,
            'https://new.example.com',
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testRefreshesRegistrationForEveryApp(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));
        $shopIdProvider->expects($this->once())
            ->method('regenerateAndSetShopId')
            ->with('shop-id');

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$calledApps, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->with('https://new.example.com')
            ->willReturn(new VerificationState(VerificationStatus::PASS, 1, new \DateTimeImmutable()));

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new NullLogger(),
            $verifier,
            'https://new.example.com',
        );

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $calledApps);
    }

    public function testKeepsTheShopIdWhenTheNewAppUrlFailsVerification(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));
        $shopIdProvider->expects($this->never())->method('regenerateAndSetShopId');

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())->method('refreshRegistration');

        $strategy = new MoveShopPermanentlyStrategy(
            new StaticEntityRepository([]),
            $appManager,
            $shopIdProvider,
            new NullLogger(),
            self::verifierReturning(VerificationStatus::HARD_FAIL, 'Unexpected response'),
            'https://new.example.com',
        );

        $this->expectExceptionObject(AppException::appUrlFailedVerification('https://new.example.com', 'Unexpected response'));

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testContinuesWithRemainingAppsAndReportsFailuresTogether(): void
    {
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');
        $exception = new \RuntimeException('Could not reach app server');

        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('shop-id'), new FingerprintComparisonResult([], [], 75)));

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function () use (&$calls, $exception): void {
                if (++$calls === 1) {
                    throw $exception;
                }
            });

        $logger = new TestHandler();

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new MoveShopPermanentlyStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new Logger('test', [$logger]),
            self::verifierReturning(VerificationStatus::PASS),
            'https://new.example.com',
        );

        $this->expectExceptionObject(AppException::shopMoveFailed(['app-one']));

        try {
            $strategy->resolve(Context::createDefaultContext());
        } finally {
            $records = $logger->getRecords();
            static::assertCount(1, $records);
            static::assertSame('Failed to re-register app after shop ID change.', $records[0]->message);
            static::assertSame('app-one', $records[0]->context['appName']);
            static::assertSame($exception, $records[0]->context['exception']);
        }
    }

    private static function verifierReturning(VerificationStatus $status, ?string $info = null): AppUrlVerifier
    {
        $verifier = static::createStub(AppUrlVerifier::class);
        $verifier->method('forceVerify')->willReturn(new VerificationState($status, 1, new \DateTimeImmutable(), $info));

        return $verifier;
    }
}
