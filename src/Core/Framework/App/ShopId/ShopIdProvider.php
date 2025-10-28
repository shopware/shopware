<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Doctrine\DBAL\Connection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @phpstan-import-type ShopIdV1Config from ShopId
 * @phpstan-import-type ShopIdV2Config from ShopId
 */
#[Package('framework')]
class ShopIdProvider
{
    final public const SHOP_ID_SYSTEM_CONFIG_KEY = 'core.app.shopId';
    final public const SHOP_ID_SYSTEM_CONFIG_KEY_V2 = 'core.app.shopIdV2';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly FingerprintGenerator $fingerprintGenerator
    ) {
    }

    /**
     * @throws AppUrlChangeDetectedException
     */
    public function getShopId(): string
    {
        $shopId = $this->fetchShopIdFromSystemConfig() ?? $this->regenerateAndSetShopId();

        if ($this->hasAppUrlChanged($shopId)) {
            if ($this->hasAppsRegisteredAtAppServers()) {
                throw new AppUrlChangeDetectedException(
                    $shopId->getFingerprint(AppUrl::IDENTIFIER) ?? '',
                    $this->loadAppUrlFromEnvironment(),
                    $shopId
                );
            }

            // if the shop does not have any apps we can update the existing shop id value
            // with the new APP_URL as no app knows the shop id
            $this->regenerateAndSetShopId($shopId->id);
        }

        return $shopId->id;
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
        $this->systemConfigService->delete(self::SHOP_ID_SYSTEM_CONFIG_KEY);
        $this->systemConfigService->delete(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2);

        $this->eventDispatcher->dispatch(new ShopIdDeletedEvent());
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

        $this->systemConfigService->set(self::SHOP_ID_SYSTEM_CONFIG_KEY_V2, (array) $shopId);
        $this->eventDispatcher->dispatch(new ShopIdChangedEvent($shopId, $oldShopId));
    }

    private function hasAppsRegisteredAtAppServers(): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) FROM app WHERE app_secret IS NOT NULL') > 0;
    }

    private function hasAppUrlChanged(ShopId $shopId): bool
    {
        return $this->fingerprintGenerator
                ->compare($shopId->fingerprints)
                ->getMismatchingFingerprint(AppUrl::IDENTIFIER) instanceof FingerprintMismatch;
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

    private function loadAppUrlFromEnvironment(): string
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');

        if (!\is_string($appUrl)) {
            throw AppException::appUrlNotConfigured();
        }

        return $appUrl;
    }
}
