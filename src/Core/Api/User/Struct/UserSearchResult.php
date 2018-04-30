<?php declare(strict_types=1);

namespace Shopware\Api\User\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\Api\User\Collection\UserBasicCollection;

class UserSearchResult extends UserBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
