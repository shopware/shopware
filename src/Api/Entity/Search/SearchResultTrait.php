<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\ArrayStruct;

trait SearchResultTrait
{
    /**
     * @var AggregationResult|null
     */
    protected $aggregationResult;

    /**
     * @var UuidSearchResult
     */
    protected $uuidResult;

    public function getAggregations(): array
    {
        return $this->aggregationResult ? $this->aggregationResult->getAggregations() : [];
    }

    public function getTotal(): int
    {
        return $this->uuidResult->getTotal();
    }

    public function getCriteria(): Criteria
    {
        return $this->uuidResult->getCriteria();
    }

    public function getContext(): TranslationContext
    {
        return $this->uuidResult->getContext();
    }

    public function getAggregationResult(): ?AggregationResult
    {
        return $this->aggregationResult;
    }

    public function getUuidResult(): UuidSearchResult
    {
        return $this->uuidResult;
    }

    public static function createFromResults(
        UuidSearchResult $uuids,
        EntityCollection $entities,
        ?AggregationResult $aggregations
    ) {
        $self = new static($entities->getElements());

        $search = $uuids->getData();

        /** @var Entity $element */
        foreach ($entities->getElements() as $element) {
            if (!array_key_exists($element->getUuid(), $search)) {
                continue;
            }
            $data = $search[$element->getUuid()];

            $element->addExtension('search', new ArrayStruct($data));
        }

        $self->aggregationResult = $aggregations;
        $self->uuidResult = $uuids;

        return $self;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['total'] = $this->getTotal();
        $data['aggregations'] = $this->getAggregations();

        return $data;
    }
}
