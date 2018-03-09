<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Context\Struct\ShopContext;

interface RepositoryInterface
{
    /**
     * @param Criteria    $criteria
     * @param ShopContext $context
     *
     * @return AggregationResult
     */
    public function aggregate(Criteria $criteria, ShopContext $context);

    /**
     * @param Criteria    $criteria
     * @param ShopContext $context
     *
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, ShopContext $context);

    /**
     * @param Criteria    $criteria
     * @param ShopContext $context
     *
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, ShopContext $context);

    /**
     * @param array       $ids
     * @param ShopContext $context
     *
     * @return EntityCollection
     */
    public function readBasic(array $ids, ShopContext $context);

    /**
     * @param array       $ids
     * @param ShopContext $context
     *
     * @return EntityCollection
     */
    public function readDetail(array $ids, ShopContext $context);

    public function update(array $data, ShopContext $context): GenericWrittenEvent;

    public function upsert(array $data, ShopContext $context): GenericWrittenEvent;

    public function create(array $data, ShopContext $context): GenericWrittenEvent;

    public function delete(array $data, ShopContext $context): GenericWrittenEvent;

    public function createVersion(string $id, ShopContext $context, ?string $name = null, ?string $versionId = null): string;

    public function merge(string $versionId, ShopContext $context): void;
}
