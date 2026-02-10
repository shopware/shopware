<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Exception\EmptyQueryException;

#[Package('framework')]
class AdminElasticsearchEntitySearcher implements EntitySearcherInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntitySearcherInterface $decorated,
        private readonly AdminSearchRegistry $registry,
        private readonly AdminElasticsearchHelper $helper,
        private readonly AdminSearcher $searcher,
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->allowAdminEsSearch($definition, $context, $criteria)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        if ($criteria->getLimit() === 0) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        try {
            return $this->searcher->searchIds(
                $definition->getEntityName(),
                $criteria,
                $context
            );
        } catch (\Throwable $e) {
            if ($e instanceof EmptyQueryException) {
                return new IdSearchResult(0, [], $criteria, $context);
            }

            $this->helper->logAndThrowException($e);

            return $this->decorated->search($definition, $criteria, $context);
        }
    }

    private function allowAdminEsSearch(EntityDefinition $definition, Context $context, Criteria $criteria): bool
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return false;
        }

        if (!empty($criteria->getIds())) {
            return false;
        }

        if (!$this->helper->isEnabled()) {
            return false;
        }

        // if no filters, aggregations, queries etc, we can use es
        if ($criteria->getTerm() && $criteria->getAllFields() === []) {
            return true;
        }

        if (!$this->registry->hasIndexer($definition->getEntityName())) {
            return false;
        }

        $indexer = $this->registry->getIndexer($definition->getEntityName());

        // no field is marked for ES index, skip it
        if ($indexer->mapping([]) === []) {
            return false;
        }

        // if criteria contains unsupported fields, we cannot use es
        if (\count(array_diff(
            $criteria->getAllFields(),
            $indexer->getSupportedSearchFields()
        )) > 0) {
            return false;
        }

        return true;
    }
}
