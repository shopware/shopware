<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('checkout')]
class InAppPurchaseCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        try {
            $connection = $container->get(Connection::class);
            /** @var array<string, string> $inAppPurchases */
            $inAppPurchases = $connection->fetchAllKeyValue('
                SELECT `identifier`, LOWER(HEX(IFNULL(`app_id`, `plugin_id`))) AS extension_id
                FROM in_app_purchase
                WHERE `active` = 1
            ');
        } catch (\Exception) {
            // we don't have a database connection, so we can't fetch the active in-app purchases
            return;
        }

        InAppPurchase::registerPurchases($inAppPurchases);
    }
}
