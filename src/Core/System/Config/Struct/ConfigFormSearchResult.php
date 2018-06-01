<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Config\Collection\ConfigFormBasicCollection;

class ConfigFormSearchResult extends ConfigFormBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
