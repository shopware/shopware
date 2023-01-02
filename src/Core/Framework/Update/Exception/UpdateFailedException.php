<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Exception;

use Shopware\Core\Framework\Log\Package;
/**
 * @package system-settings
 */
#[Package('system-settings')]
class UpdateFailedException extends \RuntimeException
{
}
