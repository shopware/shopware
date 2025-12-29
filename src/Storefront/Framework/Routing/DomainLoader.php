<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader as CoreDomainLoader;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader instead.
 */
#[Package('framework')]
class DomainLoader extends CoreDomainLoader
{
}
