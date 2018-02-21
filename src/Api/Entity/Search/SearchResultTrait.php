<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\ArrayStruct;

trait SearchResultTrait
{
    /**
     * @var AggregationResult|null
     */
    protected $aggregationResult;

    /**
     * @var IdSearchResult
     */
    protected $idResult;

    public function getAggregations(): array
    {
        return $this->aggregationResult ? $this->aggregationResult->getAggregations() : [];
    }

    public function getTotal(): int
    {
        return $this->idResult->getTotal();
    }

    public function getCriteria(): Criteria
    {
        return $this->idResult->getCriteria();
    }

    public function getContext(): ShopContext
    {
        return $this->idResult->getContext();
    }

    public function getAggregationResult(): ?AggregationResult
    {
        return $this->aggregationResult;
    }

    public function getIdResult(): IdSearchResult
    {
        return $this->idResult;
    }

    public static function createFromResults(
        IdSearchResult $ids,
        EntityCollection $entities,
        ?AggregationResult $aggregations
    ) {
        $self = new static($entities->getElements());

        $search = $ids->getData();

        /** @var Entity $element */
        foreach ($entities->getElements() as $element) {
            if (!array_key_exists($element->getId(), $search)) {
                continue;
            }
            $data = $search[$element->getId()];

            $element->addExtension('search', new ArrayStruct($data));
        }

        $self->aggregationResult = $aggregations;
        $self->idResult = $ids;

        return $self;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $data['total'] = $this->getTotal();
        $data['aggregations'] = $this->getAggregations();

        return $data;
    }

    public function setUuidResult(UuidSearchResult $uuidResult): void
    {
        $this->uuidResult = $uuidResult;
    }

    public function setAggregationResult(?AggregationResult $aggregationResult): void
    {
        $this->aggregationResult = $aggregationResult;
    }
}
