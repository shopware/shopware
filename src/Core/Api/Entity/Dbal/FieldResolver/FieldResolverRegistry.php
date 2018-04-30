<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal\FieldResolver;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\Field\Field;
use Shopware\Context\Struct\ApplicationContext;

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

    public function resolve(string $definition, string $root, Field $field, QueryBuilder $query, ApplicationContext $context, EntityDefinitionQueryHelper $queryHelper, $raw = false): void
    {
        foreach ($this->resolvers as $resolver) {
            $resolver->resolve($definition, $root, $field, $query, $context, $queryHelper, $raw);
        }
    }
}
