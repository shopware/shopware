<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Struct;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;

class LanguageSearchResult extends LanguageBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
