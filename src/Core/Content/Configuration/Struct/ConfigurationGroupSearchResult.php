<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Struct;

use Shopware\Core\Content\Configuration\Collection\ConfigurationGroupBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class ConfigurationGroupSearchResult extends ConfigurationGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
