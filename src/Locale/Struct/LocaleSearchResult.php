<?php declare(strict_types=1);

namespace Shopware\Locale\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Locale\Collection\LocaleBasicCollection;

class LocaleSearchResult extends LocaleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
