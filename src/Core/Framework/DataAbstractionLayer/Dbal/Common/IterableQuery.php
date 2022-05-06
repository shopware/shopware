<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 */
interface IterableQuery
{
    public function fetch(): array;

    public function fetchCount(): int;

    public function getQuery(): QueryBuilder;

    public function getOffset(): array;
}
