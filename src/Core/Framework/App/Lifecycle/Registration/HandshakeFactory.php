<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;

final class HandshakeFactory
{
    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    /**
     * @var StoreClient
     */
    private $storeClient;

    public function __construct(string $shopUrl, ShopIdProvider $shopIdProvider, StoreClient $storeClient)
    {
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
        $this->storeClient = $storeClient;
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
                $shopId
            );
        }

        return new StoreHandshake(
            $this->shopUrl,
            $setup->getRegistrationUrl(),
            $metadata->getName(),
            $shopId,
            $this->storeClient
        );
    }
}
