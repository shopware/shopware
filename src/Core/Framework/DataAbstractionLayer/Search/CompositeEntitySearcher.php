<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\Api\Converter\ConverterService;
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
     * @var ConverterService
     */
    private $converterService;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ConverterService $converterService,
        iterable $definitions
    ) {
        $this->definitionRegistry = $definitionRegistry;
        $this->definitions = $definitions;
        $this->converterService = $converterService;
    }

    public function search(string $term, int $limit, Context $context, int $apiVersion): array
    {
        $entities = [];

        foreach ($this->definitions as $definition) {
            if (!$context->isAllowed($definition->getEntityName(), 'list')) {
                continue;
            }

            if (!$this->converterService->isAllowed($definition->getEntityName(), null, $apiVersion)) {
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
