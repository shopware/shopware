<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelException;

#[Package('discovery')]
class NoContextDataException extends SalesChannelException
{
}
