<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12349) tag:6.5.0.0 - Will be deleted. Migrations are now namespaced by major version
 */
class Migration1610523548FixCustomerColumns extends \Shopware\Core\Migration\V6_4\Migration1610523548FixCustomerColumns
{
    public function updateDestructive(Connection $connection): void
    {
    }
}
