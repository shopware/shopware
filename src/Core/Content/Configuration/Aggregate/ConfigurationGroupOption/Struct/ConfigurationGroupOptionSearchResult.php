<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Collection\ConfigurationGroupOptionBasicCollection;

class ConfigurationGroupOptionSearchResult extends ConfigurationGroupOptionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
