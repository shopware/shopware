<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing\AggregationView;

use Shopware\Framework\Struct\Struct;

class SliderAggregation extends Struct implements AggregationViewInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var float
     */
    protected $min;

    /**
     * @var float
     */
    protected $max;

    /**
     * @var string
     */
    protected $minFieldName;

    /**
     * @var string
     */
    protected $maxFieldName;

    /**
     * @var float
     */
    protected $activeMax;

    /**
     * @var float
     */
    protected $activeMin;

    /**
     * @var string|null
     */
    protected $suffix;

    /**
     * @var int
     */
    protected $digits;

    public function __construct(
        string $name,
        bool $active,
        string $label,
        float $min,
        float $max,
        float $activeMin,
        float $activeMax,
        string $minFieldName,
        string $maxFieldName,
        ?string $suffix = null,
        float $digits = 2
    ) {
        $this->name = $name;
        $this->active = $active;
        $this->label = $label;
        $this->min = $min;
        $this->activeMin = $activeMin;
        $this->minFieldName = $minFieldName;
        $this->max = $max;
        $this->activeMax = $activeMax;
        $this->maxFieldName = $maxFieldName;
        $this->suffix = $suffix;
        $this->digits = $digits;
    }

    public function getAggregationName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getMinFieldName(): string
    {
        return $this->minFieldName;
    }

    public function getMaxFieldName(): string
    {
        return $this->maxFieldName;
    }

    public function getActiveMax(): float
    {
        return $this->activeMax;
    }

    public function getActiveMin(): float
    {
        return $this->activeMin;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function getDigits(): int
    {
        return $this->digits;
    }

    public function getType(): string
    {
        return 'slider';
    }
}
