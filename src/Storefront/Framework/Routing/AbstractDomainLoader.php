<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader as CoreAbstractDomainLoader;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader instead.
 */
#[Package('framework')]
abstract class AbstractDomainLoader extends CoreAbstractDomainLoader
{
}
