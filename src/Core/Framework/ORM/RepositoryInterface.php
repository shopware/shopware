<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\IdSearchResult;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Event\EntityWriterEventContainer;

interface RepositoryInterface
{
    /**
     * @param Criteria           $criteria
     * @param \Shopware\Core\Framework\Context $context
     *
     * @return AggregationResult
     */
    public function aggregate(Criteria $criteria, Context $context);

    /**
     * @param Criteria           $criteria
     * @param \Shopware\Core\Framework\Context $context
     *
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context);

    /**
     * @param Criteria           $criteria
     * @param \Shopware\Core\Framework\Context $context
     *
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, Context $context);

    /**
     * @param array              $ids
     * @param \Shopware\Core\Framework\Context $context
     *
     * @return EntityCollection
     */
    public function readBasic(array $ids, Context $context);

    public function update(array $data, Context $context);

    public function upsert(array $data, Context $context);

    public function create(array $data, Context $context);

    public function delete(array $data, Context $context);

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, Context $context): void;
}
