<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ReinstallAppsStrategy::class)]
class ReinstallAppsStrategyTest extends TestCase
{
    private const MANIFEST_WITH_SETUP = __DIR__ . '/../Manifest/_fixtures/test';
    private const MANIFEST_WITHOUT_SETUP = __DIR__ . '/../Manifest/_fixtures/compatibility';

    public function testGetName(): void
    {
        $strategy = $this->createStrategy();

        static::assertSame(ReinstallAppsStrategy::STRATEGY_NAME, $strategy->getName());
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

    public function testResolveDeletesShopIdAndReRegistersApps(): void
    {
        $app = $this->createAppEntity('test', active: true);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())
            ->method('registerApp')
            ->with(
                static::callback(static fn (Manifest $manifest): bool => $manifest->getMetadata()->getName() === 'test'),
                $app->getId(),
                static::callback(static fn (string $secret): bool => $secret !== ''),
                static::isInstanceOf(Context::class)
            );

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents): object {
                $dispatchedEvents[] = $event;

                return $event;
            });

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app]),
        ]);

        $strategy = new ReinstallAppsStrategy(
            new StaticSourceResolver(['test' => new Filesystem(self::MANIFEST_WITH_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider,
            $eventDispatcher
        );

        $strategy->resolve(Context::createDefaultContext());

        static::assertCount(1, $appRepo->updates);
        static::assertCount(2, $dispatchedEvents);
        static::assertInstanceOf(AppInstalledEvent::class, $dispatchedEvents[0]);
        static::assertInstanceOf(AppActivatedEvent::class, $dispatchedEvents[1]);
    }

    public function testResolveDoesNotDispatchActivatedEventForInactiveApps(): void
    {
        $app = $this->createAppEntity('test', active: false);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->once())->method('registerApp');

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents): object {
                $dispatchedEvents[] = $event;

                return $event;
            });

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app]),
        ]);

        $strategy = new ReinstallAppsStrategy(
            new StaticSourceResolver(['test' => new Filesystem(self::MANIFEST_WITH_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider,
            $eventDispatcher
        );

        $strategy->resolve(Context::createDefaultContext());

        static::assertCount(1, $dispatchedEvents);
        static::assertInstanceOf(AppInstalledEvent::class, $dispatchedEvents[0]);
    }

    public function testResolveSkipsAppsWithoutSetup(): void
    {
        $app = $this->createAppEntity('minimal');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);

        $registrationService = $this->createMock(AppRegistrationService::class);
        $registrationService->expects($this->never())->method('registerApp');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app]),
        ]);

        $strategy = new ReinstallAppsStrategy(
            new StaticSourceResolver(['minimal' => new Filesystem(self::MANIFEST_WITHOUT_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider,
            $eventDispatcher
        );

        $strategy->resolve(Context::createDefaultContext());

        static::assertEmpty($appRepo->updates);
    }

    public function testResolveCollectsFailuresAndThrowsException(): void
    {
        $app1 = $this->createAppEntity('test', 'app-1');
        $app2 = $this->createAppEntity('test', 'app-2');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);

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

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            new AppCollection([$app1, $app2]),
        ]);

        $strategy = new ReinstallAppsStrategy(
            new StaticSourceResolver(['test' => new Filesystem(self::MANIFEST_WITH_SETUP)]),
            $appRepo,
            $registrationService,
            $shopIdProvider,
            $eventDispatcher
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Failed to re-register 1 app(s):');

        $strategy->resolve(Context::createDefaultContext());
    }

    private function createStrategy(): ReinstallAppsStrategy
    {
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        return new ReinstallAppsStrategy(
            new StaticSourceResolver(),
            $appRepo,
            $this->createMock(AppRegistrationService::class),
            $this->createMock(ShopIdProvider::class),
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    private function createAppEntity(string $name, ?string $id = null, bool $active = true): AppEntity
    {
        $id ??= Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $app = new AppEntity();
        $app->setId($id);
        $app->setUniqueIdentifier($id);
        $app->setName($name);
        $app->setIntegrationId($integrationId);
        $app->setActive($active);

        return $app;
    }
}
