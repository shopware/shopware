<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Struct\Struct;

class FieldResolverContext extends Struct
{
    /**
     * Contains the property path of the current field
     * `product.manufacturer`
     *
     * @var string
     */
    protected $path;

    /**
     * Contains the alias of the base table where the sql join has to be build on
     * `product.manufacturer_1`
     *
     * @var string
     */
    protected $alias;

    /**
     * Contains the field which has to be resolved
     * ManyToManyAssociationField|OneToManyAssociationField|...
     *
     * @var Field
     */
    protected $field;

    /**
     * Contains the entity definition where the field comes from
     *
     * @var EntityDefinition
     */
    protected $definition;

    /**
     * Contains the entity definition of the root table
     *
     * @var EntityDefinition
     */
    protected $rootDefinition;

    /**
     * Contains the query builder which is used to build the sql query
     *
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @var Context
     */
    protected $context;

    /**
     * Contains the criteria element which points to the provided field. In some cases this part is a JoinGroup with different DAL filters
     * to pre-restrict the join condition in mysql for to-many-association filters
     *
     * @var CriteriaPartInterface|null
     */
    protected $criteriaPart;

    public function __construct(
        string $path,
        string $alias,
        Field $field,
        EntityDefinition $definition,
        EntityDefinition $rootDefinition,
        QueryBuilder $query,
        Context $context,
        ?CriteriaPartInterface $criteriaPart
    ) {
        $this->path = $path;
        $this->alias = $alias;
        $this->field = $field;
        $this->definition = $definition;
        $this->rootDefinition = $rootDefinition;
        $this->query = $query;
        $this->context = $context;
        $this->criteriaPart = $criteriaPart;
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
