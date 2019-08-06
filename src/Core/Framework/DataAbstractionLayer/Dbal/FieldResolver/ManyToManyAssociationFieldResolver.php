<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;

class ManyToManyAssociationFieldResolver implements FieldResolverInterface
{
    /**
     * @var JoinBuilderInterface
     */
    private $joinBuilder;

    public function __construct(JoinBuilderInterface $joinBuilder)
    {
        $this->joinBuilder = $joinBuilder;
    }

    public function getJoinBuilder(): JoinBuilderInterface
    {
        return $this->joinBuilder;
    }

    public function resolve(
        EntityDefinition $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        Context $context,
        EntityDefinitionQueryHelper $queryHelper
    ): bool {
        if (!$field instanceof ManyToManyAssociationField) {
            return false;
        }

        $query->addState(EntityDefinitionQueryHelper::HAS_TO_MANY_JOIN);

        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return true;
        }
        $query->addState($alias);

        $this->getJoinBuilder()->join(
            $definition,
            JoinBuilderInterface::LEFT_JOIN,
            $field,
            $root,
            $alias,
            $query,
            $context
        );

        $reference = $field->getToManyReferenceDefinition();
        if ($definition->getClass() === $reference->getClass()) {
            return true;
        }

        if (!$reference->isInheritanceAware() || !$context->considerInheritance()) {
            return true;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $reference->getFields()->get('parent');

        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);

        return true;
    }
}
