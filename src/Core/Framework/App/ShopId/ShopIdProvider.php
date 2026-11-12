<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Util\Result;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @phpstan-import-type ShopIdV1Config from ShopId
 * @phpstan-import-type ShopIdV2Config from ShopId
 */
#[Package('framework')]
class ShopIdProvider implements ResetInterface
{
    final public const SHOP_ID_SYSTEM_CONFIG_KEY = 'core.app.shopId';
    final public const SHOP_ID_SYSTEM_CONFIG_KEY_V2 = 'core.app.shopIdV2';

    private ?ShopId $shopId = null;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly FingerprintGenerator $fingerprintGenerator
    ) {
    }

    /**
     * @throws ShopIdChangeSuggestedException
     */
    public function getShopId(): ShopId
    {
        if ($this->shopId) {
            return $this->shopId;
        }

        $shopId = $this->fetchShopIdFromSystemConfig() ?? $this->regenerateAndSetShopId();

        $fingerprintsComparison = $this->fingerprintGenerator->matchFingerprints($shopId->fingerprints);
        if (!$fingerprintsComparison->isMatching()) {
            if ($this->hasAppsRegisteredAtAppServers()) {
                throw AppException::shopIdChangeSuggested($shopId, $fingerprintsComparison);
            }

            // if the shop does not have any apps we can update the existing shop id value
            // with the new APP_URL as no app knows the shop id
            return $this->regenerateAndSetShopId($shopId->id);
        }

        return $this->shopId = $shopId;
    }

    public function regenerateAndSetShopId(?string $existingShopId = null): ShopId
    {
        $shopId = ShopId::v2(
            $existingShopId ?? Random::getAlphanumericString(16),
            $this->fingerprintGenerator->takeFingerprints(),
        );

        $this->setShopId($shopId);

        return $shopId;
    }

    public function deleteShopId(): void
    {
        $this->systemConfigService->delete(self::SHOP_ID_SYSTEM_CONFIG_KEY, null, true);
        $this->systemConfigService->delete(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2, null, false);

        $this->reset();

        $this->eventDispatcher->dispatch(new ShopIdDeletedEvent());
    }

    /**
     * @return Result<string>
     */
    public function verifyShopId(): Result
    {
        $cachedShopId = $this->fetchCachedShopId();
        $storedShopId = $this->fetchStoredShopId();

        if ($cachedShopId === $storedShopId) {
            return Result::ok();
        }

        return Result::failed(\sprintf(
            'The cached shop ID "%s" differs from the shop ID "%s" in the database. This can happen when another installation shares this cache. Give each installation its own SHOPWARE_CACHE_ID or its own cache server, then clear the cache.',
            $cachedShopId ?? 'none',
            $storedShopId ?? 'none',
        ));
    }

    public function reset(): void
    {
        $this->shopId = null;
    }

    private function setShopId(ShopId $shopId): void
    {
        $oldShopId = $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2)
            ?? $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY);
        if (\is_array($oldShopId)) {
            $oldShopId = ShopId::fromSystemConfig($oldShopId);
        } else {
            $oldShopId = null;
        }

        $this->systemConfigService->set(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2, $shopId->toArray(), null, false);

        // A regeneration can keep the id while refreshing fingerprints (APP_URL move, V1->V2 upgrade);
        // that is not a change of shop identity, so the event only fires when the id actually differs.
        if ($oldShopId?->id !== $shopId->id) {
            $this->eventDispatcher->dispatch(new ShopIdChangedEvent($shopId, $oldShopId));
        }

        $this->shopId = $shopId;
    }

    private function hasAppsRegisteredAtAppServers(): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) FROM app WHERE app_secret IS NOT NULL') > 0;
    }

    private function fetchCachedShopId(): ?string
    {
        $config = $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2);

        return \is_array($config) ? ShopId::fromSystemConfig($config)->id : null;
    }

    private function fetchStoredShopId(): ?string
    {
        ReplicaConnection::ensurePrimary();

        $value = $this->connection->fetchOne(
            'SELECT configuration_value FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => self::SHOP_ID_SYSTEM_CONFIG_KEY_V2],
        );

        if (!\is_string($value)) {
            return null;
        }

        $config = json_decode($value, true)['_value'] ?? null;

        return \is_array($config) ? ShopId::fromSystemConfig($config)->id : null;
    }

    private function fetchShopIdFromSystemConfig(): ?ShopId
    {
        /** @var ShopIdV2Config|null $shopIdV2 */
        $shopIdV2 = $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2);
        if (\is_array($shopIdV2)) {
            return ShopId::fromSystemConfig($shopIdV2);
        }

        /** @var ShopIdV1Config|null $shopIdV1 */
        $shopIdV1 = $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY);
        if (\is_array($shopIdV1)) {
            $shopIdV1 = ShopId::fromSystemConfig($shopIdV1);

            return $this->regenerateAndSetShopId($shopIdV1->id);
        }

        return null;
    }
}
