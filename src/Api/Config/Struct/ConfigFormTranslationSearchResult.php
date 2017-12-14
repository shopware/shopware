<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ConfigFormTranslationSearchResult extends ConfigFormTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
