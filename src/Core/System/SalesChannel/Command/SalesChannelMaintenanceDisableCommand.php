<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Command;

use Shopware\Core\Framework\Log\Package;
/**
 * @deprecated tag:v6.5.0 - Will be removed, use the Command from the maintenance bundle instead
 * @package sales-channel
 */
#[Package('sales-channel')]
class SalesChannelMaintenanceDisableCommand extends \Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceDisableCommand
{
}
