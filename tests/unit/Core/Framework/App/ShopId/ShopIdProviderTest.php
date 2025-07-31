<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[CoversClass(ShopIdProvider::class)]
class ShopIdProviderTest extends TestCase
{
    #[DataProvider('oldShopIdsProvider')]
    public function testGeneratesNewShopIdV2(?string $oldShopId): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturnOnConsecutiveCalls(null, $oldShopId ? (array) ShopId::v1($oldShopId) : null);
        $systemConfigService->expects($this->once())
            ->method('set')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, static::callback(function (array $config): bool {
                static::assertSame(2, $config['version'] ?? null);
                static::assertSame([], $config['fingerprints'] ?? null);

                return true;
            }));

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            $this->createMock(Connection::class),
            $this->createMock(FingerprintGenerator::class),
        );

        $shopId = $provider->getShopId();

        static::assertCount(1, $eventDispatcher->getEvents());

        $shopIdChangedEvent = $eventDispatcher->getEvents()[0] ?? null;
        static::assertInstanceOf(ShopIdChangedEvent::class, $shopIdChangedEvent);
        static::assertSame($oldShopId, $shopIdChangedEvent->oldShopId?->id);
        static::assertSame($shopId, $shopIdChangedEvent->newShopId->id);
    }

    public function testUpgradesShopIdToV2IfShopIdInSystemConfigIsV1(): void
    {
        $shopIdV1Config = [
            'value' => '1234567890',
            'app_url' => 'https://foo.bar',
        ];

        $shopIdV2Config = [
            'id' => $shopIdV1Config['value'],
            'fingerprints' => [],
            'version' => 2,
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturn($shopIdV1Config, $shopIdV2Config);
        $systemConfigService->expects($this->once())
            ->method('set')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, static::callback(function (array $config) use ($shopIdV2Config): bool {
                static::assertSame($shopIdV2Config, $config);

                return true;
            }));

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            $this->createMock(Connection::class),
            $this->createMock(FingerprintGenerator::class),
        );

        $upgradedShopId = $provider->getShopId();

        static::assertCount(1, $eventDispatcher->getEvents());

        $shopIdChangedEvent = $eventDispatcher->getEvents()[0] ?? null;
        static::assertInstanceOf(ShopIdChangedEvent::class, $shopIdChangedEvent);
        static::assertSame($shopIdV1Config['value'], $shopIdChangedEvent->oldShopId?->id);
        static::assertSame($shopIdV1Config['value'], $shopIdChangedEvent->newShopId->id);
        static::assertSame($shopIdV1Config['value'], $upgradedShopId);
    }

    public function testThrowsIfAppUrlHasChangedAndHasAppsRegisteredAtAppServers(): void
    {
        $shopId = ShopId::v2('1234567890');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturn((array) $shopId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->method('compare')
            ->willThrowException(AppException::shopIdChangeSuggested([AppUrl::IDENTIFIER]));

        $provider = new ShopIdProvider(
            $systemConfigService,
            new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
        );

        static::expectException(AppUrlChangeDetectedException::class);
        $provider->getShopId();
    }

    public function testUpdatesShopIdIfAppUrlHasChangedButHasNoAppsRegisteredAtAppServers(): void
    {
        $shopId = ShopId::v2('1234567890', [
            AppUrl::IDENTIFIER => 'https://old.url',
        ]);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturnOnConsecutiveCalls((array) $shopId, (array) $shopId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('compare')
            ->willThrowException(AppException::shopIdChangeSuggested([AppUrl::IDENTIFIER]));
        $fingerprintGenerator->expects($this->once())
            ->method('takeFingerprints')
            ->willReturn([
                AppUrl::IDENTIFIER => 'https://new.url',
            ]);

        $provider = new ShopIdProvider(
            $systemConfigService,
            new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
        );

        static::assertSame($shopId->id, $provider->getShopId());
    }

    public function testDeletesShopId(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('delete')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            $this->createMock(Connection::class),
            $this->createMock(FingerprintGenerator::class),
        );

        $provider->deleteShopId();

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(ShopIdDeletedEvent::class, $eventDispatcher->getEvents()[0]);
    }

    public static function oldShopIdsProvider(): \Generator
    {
        yield 'old shop id NOT present' => [null];
        yield 'old shop id IS present' => ['0987654321'];
    }
}
