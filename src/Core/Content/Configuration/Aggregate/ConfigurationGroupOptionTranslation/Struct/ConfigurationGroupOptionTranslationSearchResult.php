<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionTranslationSearchResult extends ConfigurationGroupOptionTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
