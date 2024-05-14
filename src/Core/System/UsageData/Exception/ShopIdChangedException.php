<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\UsageDataException;

/**
 * @deprecated tag:v6.7.0 will be removed with no replacement
 */
#[Package('data-services')]
class ShopIdChangedException extends UsageDataException
{
}
