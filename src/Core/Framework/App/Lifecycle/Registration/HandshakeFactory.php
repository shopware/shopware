<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
#[Package('core')]
class HandshakeFactory
{
    public function __construct(
        private readonly string $shopUrl,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly StoreClient $storeClient,
        private readonly string $shopwareVersion
    ) {
    }

    public function create(Manifest $manifest): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $metadata = $manifest->getMetadata();
        $appName = $metadata->getName();

        if (!$setup) {
            throw AppException::registrationFailed(
                $appName,
                sprintf('No setup for registration provided in manifest for app "%s".', $metadata->getName())
            );
        }

        $privateSecret = $setup->getSecret();

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException) {
            throw AppException::registrationFailed(
                $appName,
                'The app url changed. Please resolve how the apps should handle this change.'
            );
        }

        if ($privateSecret) {
            return new PrivateHandshake(
                $this->shopUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $shopId,
                $this->shopwareVersion
            );
        }

        return new StoreHandshake(
            $this->shopUrl,
            $setup->getRegistrationUrl(),
            $metadata->getName(),
            $shopId,
            $this->storeClient,
            $this->shopwareVersion
        );
    }
}
