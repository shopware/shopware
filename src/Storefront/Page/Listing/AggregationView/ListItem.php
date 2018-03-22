<?php

namespace Shopware\Storefront\Page\Listing\AggregationView;

use Shopware\Framework\Struct\Struct;

class ListItem extends Struct
{
    /**
     * @var string
     */
    protected $label;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var mixed
     */
    protected $value;

    public function __construct(string $label, bool $active, $value)
    {
        $this->label = $label;
        $this->active = $active;
        $this->value = $value;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getValue()
    {
        return $this->value;
    }
}