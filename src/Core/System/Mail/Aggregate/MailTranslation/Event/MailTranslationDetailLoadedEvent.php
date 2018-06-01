<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Mail\Aggregate\MailTranslation\Collection\MailTranslationDetailCollection;
use Shopware\System\Mail\Event\MailBasicLoadedEvent;

class MailTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
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
