<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Collection\ConfigurationGroupTranslationBasicCollection;

class ConfigurationGroupTranslationSearchResult extends ConfigurationGroupTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
