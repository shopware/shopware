<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractSalesChannelDomainProvider
{
    /**
     * @return array<string, string>
     */
    abstract public function fetchSalesChannelDomains(): array;
}
