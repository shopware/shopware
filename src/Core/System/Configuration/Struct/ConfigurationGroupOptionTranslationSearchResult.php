<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\System\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ConfigurationGroupOptionTranslationSearchResult extends ConfigurationGroupOptionTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
