<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;
/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 * @package core
 */
#[Package('core')]
class InvalidArgumentException extends \InvalidArgumentException
{
}
