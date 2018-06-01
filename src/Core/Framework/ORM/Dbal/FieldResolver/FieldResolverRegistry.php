<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\ORM\Dbal\QueryBuilder;
use Shopware\Core\Framework\ORM\Field\Field;

class FieldResolverRegistry
{
    /**
     * @var FieldResolverInterface[]
     */
    protected $resolvers;

    public function __construct(iterable $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function resolve(string $definition, string $root, Field $field, QueryBuilder $query, Context $context, EntityDefinitionQueryHelper $queryHelper, $raw = false): void
    {
        foreach ($this->resolvers as $resolver) {
            $resolver->resolve($definition, $root, $field, $query, $context, $queryHelper, $raw);
        }
    }
}
