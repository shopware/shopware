<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationBasicCollection;

class ConfigFormFieldTranslationSearchResult extends ConfigFormFieldTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
