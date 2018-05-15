<?php declare(strict_types=1);

namespace Shopware\Framework\ORM;

use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Context\Struct\ApplicationContext;

interface RepositoryInterface
{
    /**
     * @param Criteria           $criteria
     * @param ApplicationContext $context
     *
     * @return AggregationResult
     */
    public function aggregate(Criteria $criteria, ApplicationContext $context);

    /**
     * @param Criteria           $criteria
     * @param ApplicationContext $context
     *
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, ApplicationContext $context);

    /**
     * @param Criteria           $criteria
     * @param ApplicationContext $context
     *
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, ApplicationContext $context);

    /**
     * @param array              $ids
     * @param ApplicationContext $context
     *
     * @return EntityCollection
     */
    public function readBasic(array $ids, ApplicationContext $context);

    /**
     * @param array              $ids
     * @param ApplicationContext $context
     *
     * @return EntityCollection
     */
    public function readDetail(array $ids, ApplicationContext $context);

    public function update(array $data, ApplicationContext $context): GenericWrittenEvent;

    public function upsert(array $data, ApplicationContext $context): GenericWrittenEvent;

    public function create(array $data, ApplicationContext $context): GenericWrittenEvent;

    public function delete(array $data, ApplicationContext $context): GenericWrittenEvent;

    public function createVersion(string $id, ApplicationContext $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, ApplicationContext $context): void;
}
