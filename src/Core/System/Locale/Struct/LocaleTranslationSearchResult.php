<?php declare(strict_types=1);

namespace Shopware\System\Locale\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Locale\Collection\LocaleTranslationBasicCollection;

class LocaleTranslationSearchResult extends LocaleTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
