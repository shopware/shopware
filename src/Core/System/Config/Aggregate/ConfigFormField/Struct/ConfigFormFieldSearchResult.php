<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldBasicCollection;

class ConfigFormFieldSearchResult extends ConfigFormFieldBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
