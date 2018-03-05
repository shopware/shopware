<?php

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Configuration\Collection\ConfigurationGroupBasicCollection;

class ConfigurationGroupSearchResult extends ConfigurationGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
