<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Config\Collection\ConfigFormBasicCollection;

class ConfigFormSearchResult extends ConfigFormBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
