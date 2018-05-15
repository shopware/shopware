<?php declare(strict_types=1);

namespace Shopware\System\User\Struct;

use Shopware\Api\Entity\Search\SearchResultInterface;
use Shopware\Api\Entity\Search\SearchResultTrait;
use Shopware\System\User\Collection\UserBasicCollection;

class UserSearchResult extends UserBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
