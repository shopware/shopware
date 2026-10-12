<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Rule\LineItemProductTypeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1785810497ConvertProductStreamProductStatesFilter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785810497;
    }

    public function update(Connection $connection): void
    {
        // the product type rule and the product.type stream filter field shipped together,
        // so this class marks a codebase that can evaluate the converted filter
        if (!class_exists(LineItemProductTypeRule::class)) {
            return;
        }

        // Migration1773829001 defers the conversion to updateDestructive(), which the default
        // `safe` destructive window never reaches, so the same conversion is re-applied here
        (new Migration1773829001MigrateProductStreamProductStatesFilter())->updateDestructive($connection);
    }
}
