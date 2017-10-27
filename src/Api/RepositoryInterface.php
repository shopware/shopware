<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Api\Read\DetailReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\SearcherInterface;
use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Api\Write\WriterInterface;
use Shopware\Api\Write\WrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Collection;

interface RepositoryInterface extends SearcherInterface, BasicReaderInterface, DetailReaderInterface, WriterInterface
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
     * @return \Shopware\Api\Write\WrittenEvent
     */
    public function update(array $data, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return \Shopware\Api\Write\WrittenEvent
     */
    public function upsert(array $data, TranslationContext $context);

    /**
     * @param array              $data
     * @param TranslationContext $context
     *
     * @return WrittenEvent
     */
    public function create(array $data, TranslationContext $context);
}
