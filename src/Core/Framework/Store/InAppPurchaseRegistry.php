<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class InAppPurchaseRegistry
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function register(): void
    {
        try {
            InAppPurchase::registerPurchases($this->fetchActiveInAppPurchases());
        } catch (Exception) {
            // we don't have a database connection, so we can't fetch the active in-app purchases
        }
    }

    /**
     * @return array<string, string>
     */
    private function fetchActiveInAppPurchases(): array
    {
        /** @var array<string, string> */
        return $this->connection->fetchAllKeyValue('
            SELECT `identifier`, LOWER(HEX(IFNULL(`app_id`, `plugin_id`))) AS extensionId
            FROM in_app_purchase
            WHERE `active` = 1 AND expires_at > NOW()
        ');
    }
}
