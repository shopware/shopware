<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\System\Command;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - Will be removed, use the Command from the maintenance bundle instead
 */
#[Package('core')]
class SystemInstallCommand extends \Shopware\Core\Maintenance\System\Command\SystemInstallCommand
{
}
