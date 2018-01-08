<?php declare(strict_types=1);

namespace Shopware\Api\Entity;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Api\Entity\Search\SearchResultInterface;
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
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, TranslationContext $context);

    /**
     * @param Criteria           $criteria
     * @param TranslationContext $context
     *
     * @return SearchResultInterface
     */
    public function search(Criteria $criteria, TranslationContext $context);

    /**
     * @param array              $ids
     * @param TranslationContext $context
     *
     * @return EntityCollection
     */
    public function readBasic(array $ids, TranslationContext $context);

    /**
     * @param array              $ids
     * @param TranslationContext $context
     *
     * @return EntityCollection
     */
    public function readDetail(array $ids, TranslationContext $context);

    public function update(array $data, TranslationContext $context): GenericWrittenEvent;

    public function upsert(array $data, TranslationContext $context): GenericWrittenEvent;

    public function create(array $data, TranslationContext $context): GenericWrittenEvent;
}
