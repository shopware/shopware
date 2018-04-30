<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ConfigFormFieldSearchResult extends ConfigFormFieldBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
