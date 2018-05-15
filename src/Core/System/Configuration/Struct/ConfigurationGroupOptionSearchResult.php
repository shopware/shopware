<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\System\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ConfigurationGroupOptionSearchResult extends ConfigurationGroupOptionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
