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
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
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
#[CoversClass(ReinstallAppsStrategy::class)]
class ReinstallAppsStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            static::createStub(AppManager::class),
            static::createStub(ShopIdProvider::class),
            new NullLogger(),
            static::createStub(AppUrlVerifier::class),
        );

        static::assertSame(ReinstallAppsStrategy::STRATEGY_NAME, $strategy->getName());
        static::assertNotSame('', $strategy->getDescription());
    }

    public function testDeletesShopIdAndReregistersEveryApp(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$calledApps, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->willReturn(new VerificationState('https://new.example.com', VerificationStatus::PASS, 1, new \DateTimeImmutable()));

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new NullLogger(),
            $verifier,
        );

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $calledApps);
    }

    public function testKeepsTheShopIdWhenTheNewAppUrlFailsVerification(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->never())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())->method('reregister');

        $strategy = new ReinstallAppsStrategy(
            new StaticEntityRepository([]),
            $appManager,
            $shopIdProvider,
            new NullLogger(),
            self::verifierReturning(VerificationStatus::HARD_FAIL, 'Unexpected response'),
        );

        $this->expectExceptionObject(AppException::appUrlFailedVerification(new VerificationState('https://new.example.com', VerificationStatus::HARD_FAIL, 1, new \DateTimeImmutable(), 'Unexpected response')));

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testContinuesWithRemainingAppsAndReportsFailuresTogether(): void
    {
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');
        $exception = new \RuntimeException('Could not reach app server');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function () use (&$calls, $exception): void {
                if (++$calls === 1) {
                    throw $exception;
                }
            });

        $logger = new TestHandler();

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new Logger('test', [$logger]),
            self::verifierReturning(VerificationStatus::PASS),
        );

        $this->expectExceptionObject(AppException::reinstallAppsFailed(['app-one']));

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
        $verifier->method('forceVerify')->willReturn(new VerificationState('https://new.example.com', $status, 1, new \DateTimeImmutable(), $info));

        return $verifier;
    }
}
