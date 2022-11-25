<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\SalesChannel\Command;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @package core
 *
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'sales-channel:maintenance:disable',
    description: 'Disable maintenance mode for a sales channel',
)]
class SalesChannelMaintenanceDisableCommand extends SalesChannelMaintenanceEnableCommand
{
    protected static $defaultName = 'sales-channel:maintenance:disable';

    protected $setMaintenanceMode = false;
}
