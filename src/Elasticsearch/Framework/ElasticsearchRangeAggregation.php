<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearchDSL\Aggregation\AbstractAggregation;
use OpenSearchDSL\Aggregation\Type\BucketingTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ElasticsearchRangeAggregation extends AbstractAggregation
{
    use BucketingTrait;

    /**
     * @var array<int, array<string, float>>
     */
    private array $ranges = [];

    /**
     * @param array<int, array<string, float>> $ranges
     */
    public function __construct(
        string $name,
        string $field,
        array $ranges
    ) {
        parent::__construct($name);

        $this->setField($field);
        $this->setRanges($ranges);
    }

    /**
     * @param array<int, array<string, float>> $ranges
     */
    public function setRanges(array $ranges): void
    {
        $this->ranges = $ranges;
    }

    /**
     * @return array<int, array<string, float>>
     */
    public function getRanges(): array
    {
        return $this->ranges;
    }

    public function getType(): string
    {
        return 'ranges';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    protected function getArray(): array
    {
        return [
            'field' => $this->getField(),
            'ranges' => $this->getRanges(),
        ];
    }
}
