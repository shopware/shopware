<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;

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

    public function __construct(string $shopUrl, ShopIdProvider $shopIdProvider)
    {
        $this->shopUrl = $shopUrl;
        $this->shopIdProvider = $shopIdProvider;
    }

    public function create(Manifest $manifest): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $privateSecret = $setup->getSecret();
        if ($privateSecret) {
            $metadata = $manifest->getMetadata();

            try {
                $shopId = $this->shopIdProvider->getShopId();
            } catch (AppUrlChangeDetectedException $e) {
                throw new AppRegistrationException(
                    'The app url changed. Please resolve how the apps should handle this change.'
                );
            }

            return new PrivateHandshake(
                $this->shopUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $shopId
            );
        }

        return new StoreHandshake();
    }
}
