<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Tax\Collection\TaxBasicCollection;

class TaxSearchResult extends TaxBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
