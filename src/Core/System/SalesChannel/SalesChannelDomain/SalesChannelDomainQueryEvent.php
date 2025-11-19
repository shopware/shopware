<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannelDomain;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelDomainQueryEvent extends Event
{
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
    ) {
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
