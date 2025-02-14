<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\MaintenanceException;

/**
 * @internal
 */
#[Package('framework')]
class DatabaseSetupException extends MaintenanceException
{
}
