<?php

namespace Shopware\Currency\Struct;

use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class CurrencySearchResult extends CurrencyCollection implements SearchResultInterface
{
    use SearchResultTrait;

    /**
     * @var Currency[]
     */
    protected $elements = [];

    public function __construct(array $elements, int $total)
    {
        parent::__construct($elements);
        $this->total = $total;
    }
}

