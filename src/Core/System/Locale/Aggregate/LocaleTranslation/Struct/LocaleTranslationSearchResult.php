<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationBasicCollection;

class LocaleTranslationSearchResult extends LocaleTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
