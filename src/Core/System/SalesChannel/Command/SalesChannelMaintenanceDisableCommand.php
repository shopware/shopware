<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Command;

class SalesChannelMaintenanceDisableCommand extends SalesChannelMaintenanceEnableCommand
{
    protected static $defaultName = 'sales-channel:maintenance:disable';

    protected $setMaintenanceMode = false;
}
