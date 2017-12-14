<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Locale\Collection\LocaleTranslationBasicCollection;

class LocaleTranslationSearchResult extends LocaleTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
