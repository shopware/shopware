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
class Migration1785810496ConvertLineItemProductStatesRuleCondition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785810496;
    }

    public function update(Connection $connection): void
    {
        // only convert when this codebase can evaluate the converted condition
        if (!class_exists(LineItemProductTypeRule::class)) {
            return;
        }

        // Migration1773829000 defers the conversion to updateDestructive(), which the default
        // `safe` destructive window never reaches, so the same conversion is re-applied here
        (new Migration1773829000MigrateLineItemProductStatesRuleCondition())->updateDestructive($connection);
    }
}
