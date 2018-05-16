<?php declare(strict_types=1);

namespace Shopware\System\Snippet\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\Snippet\Collection\SnippetBasicCollection;

class SnippetSearchResult extends SnippetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
