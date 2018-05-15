<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\System\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ConfigFormFieldSearchResult extends ConfigFormFieldBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
