<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\System\Config\Collection\ConfigFormBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ConfigFormSearchResult extends ConfigFormBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
