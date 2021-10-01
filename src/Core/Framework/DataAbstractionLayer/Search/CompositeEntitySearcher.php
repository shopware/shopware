<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @major-deprecated (flag:FEATURE_NEXT_6040) - will be removed, please also remove `shopware.composite_search.definition` service tag
 */
class CompositeEntitySearcher
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var EntityDefinition[]
     */
    private $definitions;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        iterable $definitions
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->definitions = $definitions;
    }

    public function search(string $term, int $limit, Context $context): array
    {
        $entities = [];

        foreach ($this->definitions as $definition) {
            if (!$context->isAllowed($definition->getEntityName() . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->setLimit($limit);
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
            $criteria->setTerm($term);

            $repository = $this->definitionRegistry->getRepository($definition->getEntityName());

            $result = $repository->search($criteria, $context);

            $entities[] = [
                'entity' => $definition->getEntityName(),
                'total' => $result->getTotal(),
                'entities' => $result,
            ];
        }

        return $entities;
    }
}
