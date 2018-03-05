<?php

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionTranslationSearchResult extends ConfigurationGroupOptionTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
