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
        $this->elements[$view->getAggregationName()] = $view;
    }

    public function get(string $name): ?AggregationViewInterface
    {
        if (!$this->has($name)) {
            return null;
        }
        return $this->elements[$name];
    }
}