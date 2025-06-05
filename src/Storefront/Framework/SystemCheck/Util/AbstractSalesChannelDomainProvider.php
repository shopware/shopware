<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractSalesChannelDomainProvider
{
    abstract public function fetchSalesChannelDomains(): SalesChannelDomainCollection;
}
