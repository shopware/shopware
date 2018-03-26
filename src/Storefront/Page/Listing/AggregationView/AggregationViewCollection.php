<?php

namespace Shopware\Storefront\Page\Listing\AggregationView;

use Shopware\Framework\Struct\Collection;

class AggregationViewCollection extends Collection
{
    /**
     * @var AggregationViewInterface[]
     */
    protected $elements = [];

    public function add(AggregationViewInterface $view)
    {
        $this->elements[] = $view;
    }

    public function get($key): ?AggregationViewInterface
    {
        if (!$this->has($key)) {
            return null;
        }
        return $this->elements[$key];
    }
}