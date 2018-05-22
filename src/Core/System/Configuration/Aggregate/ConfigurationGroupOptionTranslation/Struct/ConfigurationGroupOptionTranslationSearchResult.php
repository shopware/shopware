<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionTranslationSearchResult extends ConfigurationGroupOptionTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
