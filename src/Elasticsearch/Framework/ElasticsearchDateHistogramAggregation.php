<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearchDSL\Aggregation\AbstractAggregation;
use OpenSearchDSL\Aggregation\Type\BucketingTrait;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class ElasticsearchDateHistogramAggregation extends AbstractAggregation
{
    use BucketingTrait;

    protected string $interval;

    protected ?string $format = null;

    public function __construct(
        string $name,
        string $field,
        string $interval,
        ?string $format = null
    ) {
        parent::__construct($name);

        $this->setField($field);
        $this->setInterval($interval);
        $this->setFormat($format);
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function setInterval(string $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getType(): string
    {
        return 'date_histogram';
    }

    /**
     * {@inheritdoc}
     */
    protected function getArray(): array
    {
        $out = [
            'field' => $this->getField(),
            'calendar_interval' => $this->getInterval(),
        ];

        if (!empty($this->format)) {
            $out['format'] = $this->format;
        }

        return $out;
    }
}
