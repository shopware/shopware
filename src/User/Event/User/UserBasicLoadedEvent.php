<?php declare(strict_types=1);

namespace Shopware\User\Event\User;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\User\Collection\UserBasicCollection;

class UserBasicLoadedEvent extends NestedEvent
{
    const NAME = 'user.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var UserBasicCollection
     */
    protected $users;

    public function __construct(UserBasicCollection $users, TranslationContext $context)
    {
        $this->context = $context;
        $this->users = $users;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getUsers(): UserBasicCollection
    {
        return $this->users;
    }
}
