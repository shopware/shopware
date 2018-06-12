<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;


interface RepositoryInterface
{
    /**
     * @param Criteria           $criteria
     * @param Context $context
     *
     * @return AggregationResult
     */
    public function aggregate(Criteria $criteria, Context $context);

    /**
     * @param Criteria           $criteria
     * @param Context $context
     *
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context);

    /**
     * @param Criteria           $criteria
     * @param Context $context
     *
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context);

    /**
     * @param ReadCriteria $criteria
     * @param Context $context
     *
     * @return EntityCollection
     */
    public function read(ReadCriteria $criteria, Context $context);

    public function update(array $data, Context $context): EntityWrittenContainerEvent;

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent;

    public function create(array $data, Context $context): EntityWrittenContainerEvent;

    public function delete(array $data, Context $context);

    public function createVersion(string $id, Context $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, Context $context): void;
}
