<?php declare(strict_types=1);

namespace Shopware\Locale\Searcher;

use Shopware\Locale\Struct\LocaleBasicCollection;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class LocaleSearchResult extends LocaleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
