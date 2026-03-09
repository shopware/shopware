<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(MoveShopPermanentlyStrategy::class)]
class MoveShopPermanentlyStrategyTest extends TestCase
{
    private const MANIFEST_WITH_SETUP = __DIR__ . '/../Manifest/_fixtures/test';
    private const MANIFEST_WITHOUT_SETUP = __DIR__ . '/../Manifest/_fixtures/compatibility';

    public function testGetName(): void
    {
        $strategy = $this->createStrategy();

        static::assertSame(MoveShopPermanentlyStrategy::STRATEGY_NAME, $strategy->getName());
    }

    public function testGetDescription(): void
    {
        $strategy = $this->createStrategy();

        static::assertIsString($strategy->getDescription());
        static::assertNotEmpty($strategy->getDescription());
    }

    public function testGetDecoratedThrowsException(): void
    {
        $strategy = $this->createStrategy();

        $this->expectException(DecorationPatternException::class);
        $strategy->getDecorated();
    }

    public function testResolveReturnsEarlyWhenNoShopIdChange(): void
    {
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('reset');
        $shopIdProvider->expects($this->once())->method('getShopId');
        $shopIdProvider->expects($this->never())->method('regenerateAndSetShopId');

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->never())->method('registerApp');

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $strategy = new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(),
            $appRepo,
            $registrationService,
            $shopIdProvider
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testResolveReRegistersAppsOnShopIdChange(): void
    {
        $app = $this->createAppEntity('test');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('reset');
        $shopIdProvider->expects($this->once())
            ->method('getShopId')
            ->willThrowException($this->createShopIdChangedException('test-shop-id'));
        $shopIdProvider->expects($this->once())
            ->method('regenerateAndSetShopId')
            ->with('test-shop-id');

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())
            ->method('registerApp')
            ->with(
                static::callback(static fn (Manifest $manifest): bool => $manifest->getMetadata()->getName() === 'test'),
                $app->getId(),
                static::callback(static fn (string $secret): bool => $secret !== ''),
                static::isInstanceOf(Context::class)
            );

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app]),
        ]);

        $strategy = new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(['test' => new Filesystem(self::MANIFEST_WITH_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider
        );

        $strategy->resolve(Context::createDefaultContext());

        static::assertCount(1, $appRepo->updates);
    }

    public function testResolveSkipsAppsWithoutSetup(): void
    {
        $app = $this->createAppEntity('minimal');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willThrowException($this->createShopIdChangedException('test-shop-id'));

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->never())->method('registerApp');

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app]),
        ]);

        $strategy = new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(['minimal' => new Filesystem(self::MANIFEST_WITHOUT_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider
        );

        $strategy->resolve(Context::createDefaultContext());

        static::assertEmpty($appRepo->updates);
    }

    public function testResolveCollectsFailuresAndThrowsException(): void
    {
        $app1 = $this->createAppEntity('test', 'app-1');
        $app2 = $this->createAppEntity('test', 'app-2');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willThrowException($this->createShopIdChangedException('test-shop-id'));

        $registrationService = $this->createMock(AppRegistrationService::class);
        $calls = 0;
        $registrationService->expects($this->exactly(2))
            ->method('registerApp')
            ->willReturnCallback(static function () use (&$calls): void {
                ++$calls;

                if ($calls === 1) {
                    throw new \RuntimeException('Could not reach app server');
                }
            });

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app1, $app2]),
        ]);

        $strategy = new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(['test' => new Filesystem(self::MANIFEST_WITH_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Failed to re-register 1 app(s):');

        $strategy->resolve(Context::createDefaultContext());
    }

    private function createStrategy(): MoveShopPermanentlyStrategy
    {
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        return new MoveShopPermanentlyStrategy(
            new StaticSourceResolver(),
            $appRepo,
            $this->createMock(AppRegistrationService::class),
            $this->createMock(ShopIdProvider::class)
        );
    }

    private function createAppEntity(string $name, ?string $id = null): AppEntity
    {
        $id ??= Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $app = new AppEntity();
        $app->setId($id);
        $app->setUniqueIdentifier($id);
        $app->setName($name);
        $app->setIntegrationId($integrationId);
        $app->setActive(true);

        return $app;
    }

    private function createShopIdChangedException(string $shopId): ShopIdChangeSuggestedException
    {
        return new ShopIdChangeSuggestedException(
            ShopId::v2($shopId),
            new FingerprintComparisonResult([], [], 100)
        );
    }
}
