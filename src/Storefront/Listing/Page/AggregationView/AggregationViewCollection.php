<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Page\AggregationView;

use Shopware\Core\Framework\Struct\Collection;

class AggregationViewCollection extends Collection
{
    /**
     * @var AggregationViewInterface[]
     */
    protected $elements = [];

    public function add(AggregationViewInterface $view): void
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
