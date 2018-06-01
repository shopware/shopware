<?php declare(strict_types=1);

namespace Shopware\System\Language\Struct;

use Shopware\System\Language\Collection\LanguageBasicCollection;
use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;

class LanguageSearchResult extends LanguageBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
