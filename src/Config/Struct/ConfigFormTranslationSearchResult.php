<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Config\Collection\ConfigFormTranslationBasicCollection;

class ConfigFormTranslationSearchResult extends ConfigFormTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
