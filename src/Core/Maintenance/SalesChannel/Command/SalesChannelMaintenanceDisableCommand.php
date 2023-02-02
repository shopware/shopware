<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

/**
 * @internal should be used over the CLI only
 */
class SalesChannelMaintenanceDisableCommand extends SalesChannelMaintenanceEnableCommand
{
    protected static $defaultName = 'sales-channel:maintenance:disable';

    protected $setMaintenanceMode = false;
}
