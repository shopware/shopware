<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Config\Aggregate\ConfigFormTranslation\Collection\ConfigFormTranslationBasicCollection;

class ConfigFormTranslationSearchResult extends ConfigFormTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
