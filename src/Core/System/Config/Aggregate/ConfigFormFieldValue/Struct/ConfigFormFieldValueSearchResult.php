<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldValue\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\Collection\ConfigFormFieldValueBasicCollection;

class ConfigFormFieldValueSearchResult extends ConfigFormFieldValueBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
