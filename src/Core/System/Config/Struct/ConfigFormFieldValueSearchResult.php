<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\System\Config\Collection\ConfigFormFieldValueBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ConfigFormFieldValueSearchResult extends ConfigFormFieldValueBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
