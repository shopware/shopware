<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\ORM\Search\SearchResultInterface;
use Shopware\Core\Framework\ORM\Search\SearchResultTrait;
use Shopware\Core\System\Snippet\Collection\SnippetBasicCollection;

class SnippetSearchResult extends SnippetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
