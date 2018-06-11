<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Event\MailAttachmentBasicLoadedEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\Event\MailTranslationBasicLoadedEvent;
use Shopware\Core\System\Mail\Collection\MailDetailCollection;

class MailDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'mail.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MailDetailCollection
     */
    protected $mails;

    public function __construct(MailDetailCollection $mails, Context $context)
    {
        $this->context = $context;
        $this->mails = $mails;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMails(): MailDetailCollection
    {
        return $this->mails;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->mails->getOrderStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->mails->getOrderStates(), $this->context);
        }
        if ($this->mails->getAttachments()->count() > 0) {
            $events[] = new MailAttachmentBasicLoadedEvent($this->mails->getAttachments(), $this->context);
        }
        if ($this->mails->getTranslations()->count() > 0) {
            $events[] = new MailTranslationBasicLoadedEvent($this->mails->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
