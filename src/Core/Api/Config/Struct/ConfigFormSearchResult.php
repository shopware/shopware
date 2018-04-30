<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Config\Collection\ConfigFormBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ConfigFormSearchResult extends ConfigFormBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
