<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Locale\Collection\LocaleBasicCollection;

class LocaleSearchResult extends LocaleBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
