<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Config\Collection\ConfigFormFieldBasicCollection;

class ConfigFormFieldSearchResult extends ConfigFormFieldBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
