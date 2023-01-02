<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Command;

use Shopware\Core\Framework\Log\Package;
/**
 * @deprecated tag:v6.5.0 - Will be removed, use the Command from the maintenance bundle instead
 *
 * @package system-settings
 */
#[Package('system-settings')]
class UserCreateCommand extends \Shopware\Core\Maintenance\User\Command\UserCreateCommand
{
}
