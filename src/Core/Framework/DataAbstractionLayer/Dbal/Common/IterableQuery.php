<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;

interface IterableQuery
{
    public function fetch(): array;

    public function fetchCount(): int;

    public function getQuery(): QueryBuilder;

    public function getOffset(): array;
}
