<?php declare(strict_types=1);

namespace Shopware\System\User\Struct;

use Shopware\Framework\ORM\Search\SearchResultInterface;
use Shopware\Framework\ORM\Search\SearchResultTrait;
use Shopware\System\User\Collection\UserBasicCollection;

class UserSearchResult extends UserBasicCollection implements SearchResultInterface
{
    use SearchResultTrait;
}
