<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use the Command from the maintenance bundle instead
 */
#[Package('core')]
class SystemUpdateFinishCommand extends \Shopware\Core\Maintenance\System\Command\SystemUpdateFinishCommand
{
}
