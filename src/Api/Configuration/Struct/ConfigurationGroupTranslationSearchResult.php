<?php

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationBasicCollection;

class ConfigurationGroupTranslationSearchResult extends ConfigurationGroupTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
