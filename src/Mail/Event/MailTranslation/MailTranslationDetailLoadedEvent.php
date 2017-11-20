<?php declare(strict_types=1);

namespace Shopware\Mail\Event\MailTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Mail\Collection\MailTranslationDetailCollection;
use Shopware\Mail\Event\Mail\MailBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;

class MailTranslationDetailLoadedEvent extends NestedEvent
{
    const NAME = 'mail_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var MailTranslationDetailCollection
     */
    protected $mailTranslations;

    public function __construct(MailTranslationDetailCollection $mailTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->mailTranslations = $mailTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
            $events[] = new ShopBasicLoadedEvent($this->mailTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
