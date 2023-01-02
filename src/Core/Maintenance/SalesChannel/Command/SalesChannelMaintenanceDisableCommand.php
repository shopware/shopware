<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 *
 * @internal should be used over the CLI only
 */
#[Package('core')]
class SalesChannelMaintenanceDisableCommand extends SalesChannelMaintenanceEnableCommand
{
    protected static $defaultName = 'sales-channel:maintenance:disable';

    protected $setMaintenanceMode = false;
}
