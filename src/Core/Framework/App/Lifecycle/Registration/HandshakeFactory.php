<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
final class HandshakeFactory
{
    private string $shopUrl;

    private ShopIdProvider $shopIdProvider;

    private StoreClient $storeClient;

    private string $shopwareVersion;

    public function __construct(
        string $shopUrl,
        ShopIdProvider $shopIdProvider,
        StoreClient $storeClient,
        string $shopwareVersion
    ) {
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
        $this->storeClient = $storeClient;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function create(Manifest $manifest): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $metadata = $manifest->getMetadata();

        if (!$setup) {
            throw new AppRegistrationException(
                sprintf('No setup for registration provided in manifest for app "%s".', $metadata->getName())
            );
        }

        $privateSecret = $setup->getSecret();

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            throw new AppRegistrationException(
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
