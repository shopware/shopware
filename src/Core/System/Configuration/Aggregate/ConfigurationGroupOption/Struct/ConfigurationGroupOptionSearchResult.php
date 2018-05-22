<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Collection\ConfigurationGroupOptionBasicCollection;

class ConfigurationGroupOptionSearchResult extends ConfigurationGroupOptionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
