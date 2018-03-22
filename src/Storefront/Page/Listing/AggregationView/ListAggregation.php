<?php

namespace Shopware\Storefront\Page\Listing\AggregationView;

class ListAggregation implements AggregationViewInterface
{
    /**
     * @var string
     */
    protected $aggregationName;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var ListItem[]
     */
    protected $items;

    public function __construct(string $aggregationName, bool $active, string $label, string $fieldName, array $items)
    {
        $this->aggregationName = $aggregationName;
        $this->active = $active;
        $this->label = $label;
        $this->fieldName = $fieldName;
        $this->items = $items;
    }

    public function getAggregationName(): string
    {
        return $this->aggregationName;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getType(): string
    {
        return 'list';
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }
}