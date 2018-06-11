<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\User\Struct\UserSearchResult;

class UserSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'user.search.result.loaded';

    /**
     * @var UserSearchResult
     */
    protected $result;

    public function __construct(UserSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
