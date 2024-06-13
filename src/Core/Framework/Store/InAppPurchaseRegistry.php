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
        private readonly Connection $connection,
    ) {
    }

    public function register(): void
    {
        try {
            /** @var array<string, string> $inAppPurchases */
            $inAppPurchases = $this->connection->fetchAllKeyValue('
                SELECT `identifier`, LOWER(HEX(IFNULL(`app_id`, `plugin_id`))) AS extension_id
                FROM in_app_purchase
                WHERE `active` = 1
            ');

            InAppPurchase::registerPurchases($inAppPurchases);
        } catch (Exception) {
            // we don't have a database connection, so we can't fetch the active in-app purchases
        }
    }
}
