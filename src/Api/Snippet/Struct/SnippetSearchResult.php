<?php declare(strict_types=1);

namespace Shopware\Api\Snippet\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\Snippet\Collection\SnippetBasicCollection;

class SnippetSearchResult extends SnippetBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
