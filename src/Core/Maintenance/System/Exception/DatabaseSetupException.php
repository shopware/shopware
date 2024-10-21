<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\MaintenanceException;

if (!Feature::isActive('v6.7.0.0')) {
    /**
     * @deprecated tag:v6.7.0 - reason:becomes-internal
     */
    #[Package('core')]
    class DatabaseSetupException extends \RuntimeException
    {
    }
} else {
    /**
     * @internal
     */
    #[Package('core')]
    class DatabaseSetupException extends MaintenanceException
    {
    }
}
