<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class NoDeprecationFoundException extends \Exception
{
}
