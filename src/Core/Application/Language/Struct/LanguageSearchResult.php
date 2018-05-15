<?php declare(strict_types=1);

namespace Shopware\Application\Language\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

class LanguageSearchResult extends LanguageBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
