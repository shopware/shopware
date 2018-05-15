<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\MailTranslation;

use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\System\Mail\Collection\MailTranslationDetailCollection;
use Shopware\System\Mail\Event\Mail\MailBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MailTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MailTranslationDetailCollection
     */
    protected $mailTranslations;

    public function __construct(MailTranslationDetailCollection $mailTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mailTranslations = $mailTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
