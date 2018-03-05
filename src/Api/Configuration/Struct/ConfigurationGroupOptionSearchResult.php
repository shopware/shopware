<?php

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;

class ConfigurationGroupOptionSearchResult extends ConfigurationGroupOptionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
