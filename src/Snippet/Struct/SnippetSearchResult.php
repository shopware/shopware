<?php declare(strict_types=1);

namespace Shopware\Snippet\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Snippet\Collection\SnippetBasicCollection;

class SnippetSearchResult extends SnippetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
