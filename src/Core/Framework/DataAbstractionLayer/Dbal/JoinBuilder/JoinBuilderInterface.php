<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @deprecated tag:v6.4.0 - Will be removed
 */
interface JoinBuilderInterface
{
    public const INNER_JOIN = 'INNER JOIN';
    public const LEFT_JOIN = 'LEFT JOIN';

    public function join(EntityDefinition $definition, string $joinType, $field, string $on, string $alias, QueryBuilder $queryBuilder, Context $context): void;
}
