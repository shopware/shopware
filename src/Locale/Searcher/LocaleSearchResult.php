<?php declare(strict_types=1);

namespace Shopware\Locale\Searcher;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Locale\Struct\LocaleBasicCollection;

class LocaleSearchResult extends LocaleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
