<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface IterableQuery
{
    /**
     * @return array<string|int, mixed>
     */
    public function fetch(): array;

    public function fetchCount(): int;

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Return type will be changed to `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder`
     */
    public function getQuery(): QueryBuilder;

    /**
     * @return array{offset: int|null}
     */
    public function getOffset(): array;
}
