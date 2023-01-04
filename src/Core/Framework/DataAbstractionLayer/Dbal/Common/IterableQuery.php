<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Will be internal
 *
 * @phpstan-type Offset array{offset: int|null}
 */
#[Package('core')]
interface IterableQuery
{
    /**
     * @return array<string|int, mixed>
     */
    public function fetch(): array;

    public function fetchCount(): int;

    public function getQuery(): QueryBuilder;

    /**
     * @return Offset
     */
    public function getOffset(): array;
}
