<?php declare(strict_types=1);

namespace Shopware\Framework\Read;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Collection;
use Shopware\Framework\Write\AbstractWrittenEvent;
use Shopware\Search\Criteria;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\UuidSearchResult;

interface RepositoryInterface
{
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
     * @return Collection
     */
    public function readBasic(array $uuids, TranslationContext $context);

    /**
     * @param array              $uuids
     * @param TranslationContext $context
     *
     * @return Collection
     */
    public function readDetail(array $uuids, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return AbstractWrittenEvent
     */
    public function update(array $data, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return AbstractWrittenEvent
     */
    public function upsert(array $data, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return AbstractWrittenEvent
     */
    public function create(array $data, TranslationContext $context);
}
