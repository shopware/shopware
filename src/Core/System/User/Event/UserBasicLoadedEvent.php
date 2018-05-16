<?php declare(strict_types=1);

namespace Shopware\System\User\Event;

use Shopware\System\User\Collection\UserBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class UserBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'user.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var UserBasicCollection
     */
    protected $users;

    public function __construct(UserBasicCollection $users, ApplicationContext $context)
    {
        $this->context = $context;
        $this->users = $users;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getUsers(): UserBasicCollection
    {
        return $this->users;
    }
}
