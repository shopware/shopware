<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldBasicCollection;

class ConfigFormFieldSearchResult extends ConfigFormFieldBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
