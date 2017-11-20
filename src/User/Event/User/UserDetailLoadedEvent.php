<?php declare(strict_types=1);

namespace Shopware\User\Event\User;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\User\Collection\UserDetailCollection;

class UserDetailLoadedEvent extends NestedEvent
{
    const NAME = 'user.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var UserDetailCollection
     */
    protected $users;

    public function __construct(UserDetailCollection $users, TranslationContext $context)
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
