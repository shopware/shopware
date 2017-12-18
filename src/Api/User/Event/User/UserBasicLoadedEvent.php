<?php declare(strict_types=1);

namespace Shopware\Api\User\Event\User;

use Shopware\Api\User\Collection\UserBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class UserBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'user.basic.loaded';

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
