<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannelDomain;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelDomainQueryEvent
{
    public function __construct(
        public readonly QueryBuilder $queryBuilder,
    ) {
    }
}
