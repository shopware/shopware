<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationBasicCollection;

class ConfigFormFieldTranslationSearchResult extends ConfigFormFieldTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
