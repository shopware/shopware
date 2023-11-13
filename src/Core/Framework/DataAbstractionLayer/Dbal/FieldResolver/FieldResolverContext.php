<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('core')]
class FieldResolverContext extends Struct
{
    public function __construct(
        /** Contains the property path of the current field, e.g. `product.manufacturer` */
        protected string $path,
        /** Contains the alias of the base table where the sql join has to be build on, e.g. `product.manufacturer_1` */
        protected string $alias,
        /** Contains the field which has to be resolved, e.g. ManyToManyAssociationField|OneToManyAssociationField|... */
        protected Field $field,
        /** Contains the entity definition where the field comes from */
        protected EntityDefinition $definition,
        /** Contains the entity definition of the root table */
        protected EntityDefinition $rootDefinition,
        /** Contains the query builder which is used to build the sql query */
        protected QueryBuilder $query,
        protected Context $context,
        /** Contains the criteria element which points to the provided field. In some cases this part is a JoinGroup with different DAL filters
         * to pre-restrict the join condition in mysql for to-many-association filters */
        protected ?CriteriaPartInterface $criteriaPart
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getRootDefinition(): EntityDefinition
    {
        return $this->rootDefinition;
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCriteriaPart(): ?CriteriaPartInterface
    {
        return $this->criteriaPart;
    }
}
