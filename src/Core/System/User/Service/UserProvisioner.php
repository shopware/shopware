<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use the Service from the maintenance bundle instead
 */
#[Package('system-settings')]
class UserProvisioner extends \Shopware\Core\Maintenance\User\Service\UserProvisioner
{
}
