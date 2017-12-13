<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Config\Collection\ConfigFormTranslationBasicCollection;

class ConfigFormTranslationSearchResult extends ConfigFormTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
