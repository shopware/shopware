<?php declare(strict_types=1);

namespace Shopware\Framework\ORM;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;

interface RepositoryInterface
{
    /**
     * @param Criteria           $criteria
     * @param \Shopware\Framework\Context $context
     *
     * @return AggregationResult
     */
    public function aggregate(Criteria $criteria, Context $context);

    /**
     * @param Criteria           $criteria
     * @param \Shopware\Framework\Context $context
     *
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context);

    /**
     * @param Criteria           $criteria
     * @param \Shopware\Framework\Context $context
     *
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, Context $context);

    /**
     * @param array              $ids
     * @param \Shopware\Framework\Context $context
     *
     * @return EntityCollection
     */
    public function readBasic(array $ids, Context $context);

    /**
     * @param array              $ids
     * @param Context $context
     *
     * @return EntityCollection
     */
    public function readDetail(array $ids, Context $context);

    public function update(array $data, Context $context): GenericWrittenEvent;

    public function upsert(array $data, Context $context): GenericWrittenEvent;

    public function create(array $data, Context $context): GenericWrittenEvent;

    public function delete(array $data, Context $context): GenericWrittenEvent;

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, Context $context): void;
}
