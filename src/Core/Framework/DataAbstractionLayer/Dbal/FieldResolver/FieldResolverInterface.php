<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

/**
 * @deprecated tag:v6.4.0 - Replaced by `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\AbstractFieldResolver`
 */
interface FieldResolverInterface
{
    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool;

    public function getJoinBuilder(): JoinBuilderInterface;
}
