<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
#[Package('framework')]
readonly class HandshakeFactory
{
    public function __construct(
        private string $shopUrl,
        private ShopIdProvider $shopIdProvider,
        private StoreClient $storeClient,
        private string $shopwareVersion,
    ) {
    }

    public function create(Manifest $manifest, AppEntity $app): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $metadata = $manifest->getMetadata();
        $appName = $metadata->getName();

        if (!$setup) {
            throw AppException::registrationFailed(
                $appName,
                \sprintf('No setup for registration provided in manifest for app "%s".', $metadata->getName())
            );
        }

        $privateSecret = $setup->getSecret();

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException $e) {
            throw AppException::registrationFailed(
                $appName,
                $e->getMessage(),
            );
        }

        // Get current app secret for re-registration (secret rotation)
        $currentAppSecret = $app->getAppSecret();

        if ($privateSecret) {
            return new PrivateHandshake(
                $this->shopUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $shopId,
                $this->shopwareVersion,
                $currentAppSecret
            );
        }

        return new StoreHandshake(
            $this->shopUrl,
            $setup->getRegistrationUrl(),
            $metadata->getName(),
            $shopId,
            $this->storeClient,
            $this->shopwareVersion,
            $currentAppSecret
        );
    }
}
