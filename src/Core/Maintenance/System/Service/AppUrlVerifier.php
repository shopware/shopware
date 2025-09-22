<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerifier
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ShopIdProvider $shopIdProvider,
    ) {
    }

    public function isAppUrlReachable(): bool
    {
        $shopId = $this->shopIdProvider->getShopIdUnchecked();

        return !$shopId->urlVerificationStatus->failed();
    }

    public function hasAppsThatNeedAppUrl(): bool
    {
        $foundApp = $this->connection->fetchOne('SELECT 1 FROM app WHERE app_secret IS NOT NULL');

        return $foundApp === '1';
    }
}
