<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\System\Configuration\Collection\ConfigurationGroupBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ConfigurationGroupSearchResult extends ConfigurationGroupBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
