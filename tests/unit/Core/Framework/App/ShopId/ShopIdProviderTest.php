<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopware\Core\Framework\App\ShopId\FingerprintMismatch;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopIdProvider::class)]
class ShopIdProviderTest extends TestCase
{
    public function testGeneratesNewShopIdV2WhenNoOldShopIdPresent(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($matcher = $this->exactly(6))
            ->method('get')
            ->willReturnCallback(static function (...$parameters) use ($matcher) {
                if ($matcher->numberOfInvocations() === 1 || $matcher->numberOfInvocations() === 3 || $matcher->numberOfInvocations() === 5) {
                    static::assertSame(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, $parameters[0]);

                    return null;
                }

                if ($matcher->numberOfInvocations() === 2 || $matcher->numberOfInvocations() === 4 || $matcher->numberOfInvocations() === 6) {
                    static::assertSame(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, $parameters[0]);

                    return null;
                }

                static::fail(\sprintf('SystemConfigService was not expected to be called more than %s times', $matcher->numberOfInvocations()));
            });
        $systemConfigService->expects($this->exactly(2))
            ->method('set')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, static::callback(static function (array $config): bool {
                static::assertSame(2, $config['version'] ?? null);
                static::assertSame([], $config['fingerprints'] ?? null);

                return true;
            }));

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            static::createStub(Connection::class),
            static::createStub(FingerprintGenerator::class),
            static::createStub(AppRetainedSecretGateway::class),
        );

        $shopId = $provider->getShopId();

        static::assertCount(2, $eventDispatcher->getEvents());

        $shopIdChangedEvent = $eventDispatcher->getEvents()[0] ?? null;
        static::assertInstanceOf(ShopIdChangedEvent::class, $shopIdChangedEvent);
        static::assertNull($shopIdChangedEvent->oldShopId);
        static::assertSame($shopId->id, $shopIdChangedEvent->newShopId->id);
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
        $systemConfigService->expects($matcher = $this->exactly(6))
            ->method('get')
            ->willReturnCallback(static function (...$parameters) use ($matcher, $shopIdV1Config) {
                if ($matcher->numberOfInvocations() === 1 || $matcher->numberOfInvocations() === 3 || $matcher->numberOfInvocations() === 5) {
                    static::assertSame(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, $parameters[0]);

                    return null;
                }

                if ($matcher->numberOfInvocations() === 2 || $matcher->numberOfInvocations() === 4 || $matcher->numberOfInvocations() === 6) {
                    static::assertSame(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, $parameters[0]);

                    return $shopIdV1Config;
                }

                static::fail(\sprintf('SystemConfigService was not expected to be called more than %s times', $matcher->numberOfInvocations()));
            });
        $systemConfigService->expects($this->exactly(2))
            ->method('set')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, $shopIdV2Config);

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            static::createStub(Connection::class),
            static::createStub(FingerprintGenerator::class),
            static::createStub(AppRetainedSecretGateway::class),
        );

        $upgradedShopId = $provider->getShopId();

        // The id is preserved across the V1->V2 upgrade, so no ShopIdChangedEvent is dispatched.
        static::assertCount(0, $eventDispatcher->getEvents());
        static::assertSame($shopIdV1Config['value'], $upgradedShopId->id);
    }

    public function testThrowsIfFingerprintsHaveChangedAndHasAppsRegisteredAtAppServers(): void
    {
        $shopId = ShopId::v2('1234567890');

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2)
            ->willReturn((array) $shopId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(1);

        $fingerprintGenerator = static::createStub(FingerprintGenerator::class);
        $fingerprintGenerator->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult(
                [],
                [
                    AppUrl::IDENTIFIER => new FingerprintMismatch(
                        AppUrl::IDENTIFIER,
                        'https://old.url',
                        'https://new.url',
                        100,
                    ),
                ],
                75,
            ));

        $provider = new ShopIdProvider(
            $systemConfigService,
            new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
            static::createStub(AppRetainedSecretGateway::class),
        );

        static::expectException(ShopIdChangeSuggestedException::class);
        $provider->getShopId();
    }

    public function testKeepsThrowingWhileFingerprintsStillMismatch(): void
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn((array) ShopId::v2('1234567890'));

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $fingerprintGenerator = static::createStub(FingerprintGenerator::class);
        $fingerprintGenerator->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult(
                [],
                [
                    AppUrl::IDENTIFIER => new FingerprintMismatch(
                        AppUrl::IDENTIFIER,
                        'https://old.url',
                        'https://new.url',
                        100,
                    ),
                ],
                75,
            ));

        $provider = new ShopIdProvider(
            $systemConfigService,
            new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
            static::createStub(AppRetainedSecretGateway::class),
        );

        try {
            $provider->getShopId();
            static::fail('Expected the first call to suggest a ShopId change');
        } catch (ShopIdChangeSuggestedException) {
        }

        static::expectException(ShopIdChangeSuggestedException::class);
        $provider->getShopId();
    }

    public function testUpdatesShopIdIfFingerprintsHaveChangedButHasNoAppsRegisteredAtAppServers(): void
    {
        $shopId = ShopId::v2('1234567890', [
            AppUrl::IDENTIFIER => 'https://old.url',
        ]);

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2)
            ->willReturnOnConsecutiveCalls((array) $shopId, (array) $shopId);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(0);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult(
                [],
                [
                    AppUrl::IDENTIFIER => new FingerprintMismatch(
                        AppUrl::IDENTIFIER,
                        'https://old.url',
                        'https://new.url',
                        100,
                    ),
                ],
                75,
            ));
        $fingerprintGenerator->expects($this->once())
            ->method('takeFingerprints')
            ->willReturn([
                AppUrl::IDENTIFIER => 'https://new.url',
            ]);

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            $connection,
            $fingerprintGenerator,
            static::createStub(AppRetainedSecretGateway::class),
        );

        static::assertSame($shopId->id, $provider->getShopId()->id);
        // Fingerprints changed but the id is reused, so this is not a shop identity change.
        static::assertCount(0, $eventDispatcher->getEvents());
    }

    public function testDeletesShopId(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($matcher = $this->exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (...$parameters) use ($matcher): void {
                if ($matcher->numberOfInvocations() === 1) {
                    static::assertSame(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, $parameters[0]);
                }

                if ($matcher->numberOfInvocations() === 2) {
                    static::assertSame(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2, $parameters[0]);
                }
            });

        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher = new CollectingEventDispatcher(),
            static::createStub(Connection::class),
            static::createStub(FingerprintGenerator::class),
            static::createStub(AppRetainedSecretGateway::class),
        );

        $provider->deleteShopId();

        static::assertCount(1, $eventDispatcher->getEvents());
        static::assertInstanceOf(ShopIdDeletedEvent::class, $eventDispatcher->getEvents()[0]);
    }

    public function testRetainsAppSecretsBeforeDeletingWhenStoredShopIdIsOwned(): void
    {
        $calls = [];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2)
            ->willReturn(ShopId::v2('stored-id', [AppUrl::IDENTIFIER => 'https://shop.test'])->toArray());
        $systemConfigService->method('delete')
            ->willReturnCallback(static function (string $key) use (&$calls): void {
                $calls[] = 'delete:' . $key;
            });

        $gateway = $this->createMock(AppRetainedSecretGateway::class);
        $gateway->expects($this->once())
            ->method('retainCandidates')
            ->willReturnCallback(static function (string $shopId) use (&$calls): void {
                $calls[] = 'retain:' . $shopId;
            });

        $provider = new ShopIdProvider(
            $systemConfigService,
            new CollectingEventDispatcher(),
            static::createStub(Connection::class),
            $this->fingerprintGeneratorMatching(true),
            $gateway,
        );

        $provider->deleteShopId();

        static::assertSame([
            'retain:stored-id',
            'delete:' . ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY,
            'delete:' . ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY_V2,
        ], $calls);
    }

    public function testRetainsNothingWhenFingerprintsMismatch(): void
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('get')
            ->willReturn(ShopId::v2('stored-id', [AppUrl::IDENTIFIER => 'https://other.test'])->toArray());

        $gateway = $this->createMock(AppRetainedSecretGateway::class);
        $gateway->expects($this->never())->method('retainCandidates');

        $eventDispatcher = new CollectingEventDispatcher();
        $provider = new ShopIdProvider(
            $systemConfigService,
            $eventDispatcher,
            static::createStub(Connection::class),
            $this->fingerprintGeneratorMatching(false),
            $gateway,
        );

        $provider->deleteShopId();

        static::assertCount(1, $eventDispatcher->getEvents());
    }

    private function fingerprintGeneratorMatching(bool $matching): FingerprintGenerator
    {
        $mismatches = $matching ? [] : [
            AppUrl::IDENTIFIER => new FingerprintMismatch(AppUrl::IDENTIFIER, 'https://other.test', 'https://shop.test', 100),
        ];

        $fingerprintGenerator = static::createStub(FingerprintGenerator::class);
        $fingerprintGenerator->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([], $mismatches, $matching ? 100 : 0));

        return $fingerprintGenerator;
    }
}
