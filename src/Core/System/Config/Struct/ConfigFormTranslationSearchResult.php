<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\System\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;

class ConfigFormTranslationSearchResult extends ConfigFormTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
