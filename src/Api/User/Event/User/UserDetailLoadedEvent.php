<?php declare(strict_types=1);

namespace Shopware\Api\User\Event\User;

use Shopware\Api\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Api\User\Collection\UserDetailCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class UserDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'user.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var UserDetailCollection
     */
    protected $users;

    public function __construct(UserDetailCollection $users, ApplicationContext $context)
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

    public function getUsers(): UserDetailCollection
    {
        return $this->users;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->users->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->users->getLocales(), $this->context);
        }
        if ($this->users->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->users->getMedia(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
