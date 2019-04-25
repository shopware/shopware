<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;

class FieldResolverRegistry
{
    /**
     * @var FieldResolverInterface[]
     */
    private $resolvers;

    public function __construct(iterable $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): void {
        foreach ($this->resolvers as $resolver) {
            $handled = $resolver->resolve($definition, $root, $field, $query, $context, $queryHelper);

            if ($handled) {
                return;
            }
        }
    }
}
