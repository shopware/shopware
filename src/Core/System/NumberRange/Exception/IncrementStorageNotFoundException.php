<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\NumberRangeException;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use NumberRangeException::incrementStorageNotFound() instead
 */
#[Package('framework')]
class IncrementStorageNotFoundException extends NumberRangeException
{
}
