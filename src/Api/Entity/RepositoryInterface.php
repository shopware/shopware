<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\UuidSearchResult;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Context\Struct\TranslationContext;

interface RepositoryInterface
{
    /**
     * @param Criteria           $criteria
     * @param TranslationContext $context
     *
     * @return AggregationResult
     */
    public function aggregate(Criteria $criteria, TranslationContext $context);

    /**
     * @param Criteria           $criteria
     * @param TranslationContext $context
     *
     * @return UuidSearchResult
     */
    public function searchUuids(Criteria $criteria, TranslationContext $context);

    /**
     * @param Criteria           $criteria
     * @param TranslationContext $context
     *
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, TranslationContext $context);

    /**
     * @param array              $uuids
     * @param TranslationContext $context
     *
     * @return EntityCollection
     */
    public function readBasic(array $uuids, TranslationContext $context);

    /**
     * @param array              $uuids
     * @param TranslationContext $context
     *
     * @return EntityCollection
     */
    public function readDetail(array $uuids, TranslationContext $context);

    public function update(array $data, TranslationContext $context): GenericWrittenEvent;

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent;

    public function create(array $data, TranslationContext $context): GenericWrittenEvent;
}
