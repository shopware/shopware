<?php declare(strict_types=1);

namespace Shopware\Api\User\Event\User;

use Shopware\Api\User\Struct\UserSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class UserSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'user.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
