<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Config\Collection\ConfigFormFieldValueBasicCollection;

class ConfigFormFieldValueSearchResult extends ConfigFormFieldValueBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
