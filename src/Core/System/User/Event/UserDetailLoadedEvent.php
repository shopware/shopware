<?php declare(strict_types=1);

namespace Shopware\System\User\Event;

use Shopware\Framework\Context;
use Shopware\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\System\User\Collection\UserDetailCollection;

class UserDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'user.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var UserDetailCollection
     */
    protected $users;

    public function __construct(UserDetailCollection $users, Context $context)
    {
        $this->context = $context;
        $this->users = $users;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
