<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

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

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ApiVersionConverter $apiVersionConverter,
        iterable $definitions
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->definitions = $definitions;
        $this->apiVersionConverter = $apiVersionConverter;
    }

    public function search(string $term, int $limit, Context $context, int $apiVersion): array
    {
        $entities = [];

        foreach ($this->definitions as $definition) {
            if (!$context->isAllowed($definition->getEntityName() . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            if (!$this->apiVersionConverter->isAllowed($definition->getEntityName(), null, $apiVersion)) {
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
