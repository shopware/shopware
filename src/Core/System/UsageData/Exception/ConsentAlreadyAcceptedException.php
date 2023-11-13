<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\UsageDataException;

/**
 * @internal
 */
#[Package('merchant-services')]
class ConsentAlreadyAcceptedException extends UsageDataException
{
}
