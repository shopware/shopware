<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search;

use Shopware\Framework\ORM\Entity;
use Shopware\Framework\ORM\EntityCollection;
use Shopware\Framework\ORM\Search\Aggregation\AggregationResultCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\ArrayStruct;

trait SearchResultTrait
{
    /**
     * @var AggregatorResult|null
     */
    protected $aggregationResult;

    /**
     * @var IdSearchResult
     */
    protected $idResult;

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregationResult ? $this->aggregationResult->getAggregations() : new AggregationResultCollection();
    }

    public function getTotal(): int
    {
        return $this->idResult->getTotal();
    }

    public function getCriteria(): Criteria
    {
        return $this->idResult->getCriteria();
    }

    public function getContext(): ApplicationContext
    {
        return $this->idResult->getContext();
    }

    public function getAggregationResult(): ?AggregatorResult
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
        ?AggregatorResult $aggregations
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

    public function setAggregationResult(?AggregatorResult $aggregationResult): void
    {
        $this->aggregationResult = $aggregationResult;
    }
}
