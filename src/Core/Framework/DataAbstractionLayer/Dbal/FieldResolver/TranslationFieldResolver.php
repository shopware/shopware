<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder\JoinBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

class TranslationFieldResolver implements FieldResolverInterface
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
        if (!$field instanceof TranslatedField) {
            return false;
        }

        $alias = $root . '.' . $definition->getEntityName() . '_translation';
        if ($query->hasState($alias)) {
            return false;
        }
        $query->addState($alias);

        $this->getJoinBuilder()->join($definition, JoinBuilderInterface::LEFT_JOIN, $field, $root, $alias, $query, $context);

        return true;
    }
}
