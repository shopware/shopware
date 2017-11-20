<?php declare(strict_types=1);

namespace Shopware\Schema\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\Schema\Collection\SchemaVersionBasicCollection;

class SchemaVersionSearchResult extends SchemaVersionBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
