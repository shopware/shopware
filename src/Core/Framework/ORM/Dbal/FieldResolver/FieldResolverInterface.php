<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\FieldResolver;

use Shopware\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Framework\ORM\Dbal\QueryBuilder;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Context\Struct\ApplicationContext;

interface FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        ApplicationContext $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $raw
    ): void;
}
