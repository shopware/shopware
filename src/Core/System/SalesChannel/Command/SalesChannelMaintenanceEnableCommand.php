<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Command;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use the Command from the maintenance bundle instead
 */
#[Package('sales-channel')]
class SalesChannelMaintenanceEnableCommand extends \Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand
{
}
