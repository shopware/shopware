<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Listing\Collection\ListingSortingBasicCollection;

class ListingSortingSearchResult extends ListingSortingBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
