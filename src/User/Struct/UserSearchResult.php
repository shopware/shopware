<?php declare(strict_types=1);

namespace Shopware\User\Struct;

use Shopware\Api\Search\SearchResultInterface;
use Shopware\Api\Search\SearchResultTrait;
use Shopware\User\Collection\UserBasicCollection;

class UserSearchResult extends UserBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
