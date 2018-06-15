<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationDetailCollection;
use Shopware\Core\System\Mail\Event\MailBasicLoadedEvent;

class MailTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var MailTranslationDetailCollection
     */
    protected $mailTranslations;

    public function __construct(MailTranslationDetailCollection $mailTranslations, Context $context)
    {
        $this->context = $context;
        $this->mailTranslations = $mailTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailTranslations(): MailTranslationDetailCollection
    {
        return $this->mailTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->mailTranslations->getMails()->count() > 0) {
            $events[] = new MailBasicLoadedEvent($this->mailTranslations->getMails(), $this->context);
        }
        if ($this->mailTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->mailTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
