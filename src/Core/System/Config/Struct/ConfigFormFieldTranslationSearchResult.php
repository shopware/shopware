<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\System\Config\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class ConfigFormFieldTranslationSearchResult extends ConfigFormFieldTranslationBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
